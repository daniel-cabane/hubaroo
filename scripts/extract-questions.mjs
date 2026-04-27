/**
 * Extract individual question images from a Kangourou PDF paper.
 *
 * Usage: node scripts/extract-questions.mjs <pdfPath> <outputDir> <year> <level>
 *
 * Outputs JSON to stdout: { success: true, questions: [...] } or { success: false, error: "..." }
 */

import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';
import * as mupdf from 'mupdf';
import sharp from 'sharp';
import fs from 'fs';
import path from 'path';

const RENDER_SCALE = 3;
const PADDING_ABOVE = 20; // PDF units above question number baseline to show full square box
const BOTTOM_GAP = 10;    // Extra PDF units gap above next question (clears illustrations extending above number)
const EDGE_SCAN_BAND = 4;
const EDGE_ROW_RATIO_THRESHOLD = 0.0015;
const EDGE_BAND_RATIO_THRESHOLD = 0.01;
const EDGE_RUN_RATIO_THRESHOLD = 0.01;
const TOP_EXPANSION_STEP = 12;
const BOTTOM_EXPANSION_STEP = 12;
const MAX_TOP_EXPANSION = 48;
const MAX_ADJUSTMENT_PASSES = 6;

async function main() {
    const [pdfPath, outputDir, year, level] = process.argv.slice(2);

    if (!pdfPath || !outputDir || !year || !level) {
        throw new Error('Usage: node extract-questions.mjs <pdfPath> <outputDir> <year> <level>');
    }

    fs.mkdirSync(outputDir, { recursive: true });

    const fileBuffer = fs.readFileSync(pdfPath);

    // Step 1: Find question number positions and footer markers using pdfjs-dist
    const { questions: questionPositions, footerPositions, sectionHeaderPositions } = await findQuestionPositions(fileBuffer);

    if (questionPositions.length < 24) {
        throw new Error(`Found only ${questionPositions.length} question positions, expected at least 24`);
    }

    // Step 2: Render pages using mupdf (proper PDF renderer)
    const mupdfDoc = mupdf.Document.openDocument(fileBuffer, 'application/pdf');
    const pageBuffers = new Map();
    const pageDimensions = new Map();

    const pagesNeeded = new Set(questionPositions.map(q => q.page));

    for (const pageNum of pagesNeeded) {
        const page = mupdfDoc.loadPage(pageNum - 1); // mupdf is 0-indexed
        const pixmap = page.toPixmap(
            mupdf.Matrix.scale(RENDER_SCALE, RENDER_SCALE),
            mupdf.ColorSpace.DeviceRGB,
            false,
            true,
        );
        pageBuffers.set(pageNum, pixmap.asPNG());
        pageDimensions.set(pageNum, {
            width: pixmap.getWidth(),
            height: pixmap.getHeight(),
        });
    }

    // Step 3: Crop individual questions
    const results = [];

    for (let i = 0; i < questionPositions.length; i++) {
        const q = questionPositions[i];
        const dims = pageDimensions.get(q.page);
        const pdfPageHeight = dims.height / RENDER_SCALE;
        const pageBuffer = Buffer.from(pageBuffers.get(q.page));

        // Top of crop: above the question number square box
        let topPx = Math.max(0, Math.floor((pdfPageHeight - q.pdfY - PADDING_ABOVE) * RENDER_SCALE));

        // Bottom of crop: just above next question's square, or section header, or footer, or page bottom
        let bottomPx;
        let maxBottomPx;
        const next = questionPositions[i + 1];
        if (next && next.page === q.page) {
            // Check if there's a section header between this question and the next
            const sectionY = sectionHeaderPositions[q.page];
            if (sectionY !== undefined && sectionY < q.pdfY && sectionY > next.pdfY) {
                // Section header is between these two questions — crop above it
                bottomPx = Math.floor((pdfPageHeight - sectionY) * RENDER_SCALE);
                maxBottomPx = bottomPx;
            } else {
                // Crop above the next question's square with a small gap
                bottomPx = Math.floor((pdfPageHeight - next.pdfY - PADDING_ABOVE - BOTTOM_GAP) * RENDER_SCALE);
                maxBottomPx = Math.floor((pdfPageHeight - next.pdfY - PADDING_ABOVE) * RENDER_SCALE);
            }
        } else if (footerPositions[q.page] !== undefined) {
            // Last question on a page with footer — crop above the footer
            bottomPx = Math.floor((pdfPageHeight - footerPositions[q.page]) * RENDER_SCALE);
            maxBottomPx = bottomPx;
        } else {
            bottomPx = dims.height;
            maxBottomPx = dims.height;
        }

        bottomPx = Math.min(bottomPx, dims.height);
        maxBottomPx = Math.min(maxBottomPx, dims.height);

        ({ topPx, bottomPx } = await expandCropBounds({
            pageBuffer,
            width: dims.width,
            topPx,
            bottomPx,
            minTopPx: Math.max(0, topPx - MAX_TOP_EXPANSION),
            maxBottomPx,
        }));

        const height = bottomPx - topPx;

        if (height <= 0) {
            throw new Error(`Invalid crop for Q${q.num}: top=${topPx}, bottom=${bottomPx}`);
        }

        const filename = `${year}_${level}_q${q.num}.png`;
        const outputPath = path.join(outputDir, filename);

        await sharp(pageBuffer)
            .extract({ left: 0, top: topPx, width: dims.width, height })
            .png()
            .toFile(outputPath);

        results.push({
            questionNumber: q.num,
            image: `questions/${filename}`,
        });
    }

    console.log(JSON.stringify({
        success: true,
        count: results.length,
        questions: results,
    }));
}

async function expandCropBounds({ pageBuffer, width, topPx, bottomPx, minTopPx, maxBottomPx }) {
    let currentTopPx = topPx;
    let currentBottomPx = bottomPx;

    for (let pass = 0; pass < MAX_ADJUSTMENT_PASSES; pass++) {
        const height = currentBottomPx - currentTopPx;

        if (height <= 0) {
            break;
        }

        const { data, info } = await sharp(pageBuffer)
            .extract({ left: 0, top: currentTopPx, width, height })
            .raw()
            .toBuffer({ resolveWithObject: true });

        const touchesTop = edgeHasContent(data, info.width, info.height, 'top');
        const touchesBottom = edgeHasContent(data, info.width, info.height, 'bottom');
        let changed = false;

        if (touchesTop && currentTopPx > minTopPx) {
            currentTopPx = Math.max(minTopPx, currentTopPx - TOP_EXPANSION_STEP);
            changed = true;
        }

        if (touchesBottom && currentBottomPx < maxBottomPx) {
            currentBottomPx = Math.min(maxBottomPx, currentBottomPx + BOTTOM_EXPANSION_STEP);
            changed = true;
        }

        if (!changed) {
            break;
        }
    }

    return {
        topPx: currentTopPx,
        bottomPx: currentBottomPx,
    };
}

function edgeHasContent(data, width, height, side) {
    const scanRows = Math.min(EDGE_SCAN_BAND, height);
    const startY = side === 'top' ? 0 : height - scanRows;
    const edgeY = side === 'top' ? 0 : height - 1;
    let edgeDarkPixels = 0;
    let bandDarkPixels = 0;
    let longestRun = 0;
    let run = 0;

    for (let x = 0; x < width; x++) {
        const edgeIndex = (edgeY * width + x) * 3;
        if (isDarkPixel(data, edgeIndex)) {
            edgeDarkPixels++;
            run++;
            longestRun = Math.max(longestRun, run);
        } else {
            run = 0;
        }
    }

    for (let y = startY; y < startY + scanRows; y++) {
        for (let x = 0; x < width; x++) {
            const index = (y * width + x) * 3;
            if (isDarkPixel(data, index)) {
                bandDarkPixels++;
            }
        }
    }

    const edgeRatio = edgeDarkPixels / width;
    const bandRatio = bandDarkPixels / (width * scanRows);
    const runRatio = longestRun / width;

    return edgeRatio >= EDGE_ROW_RATIO_THRESHOLD
        || bandRatio >= EDGE_BAND_RATIO_THRESHOLD
        || runRatio >= EDGE_RUN_RATIO_THRESHOLD;
}

function isDarkPixel(data, index) {
    return (data[index] + data[index + 1] + data[index + 2]) / 3 < 245;
}

/**
 * Find question number positions using pdfjs-dist text extraction.
 *
 * Looks for standalone numbers 1-26 with a distinct font size (~15pt)
 * positioned at the left margin of the page.
 */
async function findQuestionPositions(fileBuffer) {
    const data = new Uint8Array(fileBuffer);
    const doc = await getDocument({ data }).promise;
    const positions = [];
    const footerPositions = {};
    const sectionHeaderPositions = {};

    for (let p = 1; p <= doc.numPages; p++) {
        const page = await doc.getPage(p);
        const textContent = await page.getTextContent();

        const candidates = textContent.items.filter(item => {
            const str = item.str.trim();
            if (!/^\d{1,2}$/.test(str)) {
                return false;
            }
            const num = parseInt(str);
            if (num < 1 || num > 30) {
                return false;
            }
            const fontSize = item.transform[0];
            const x = item.transform[4];

            // Question numbers: fontSize ~15, x ~53
            // Body text: fontSize ~13, x ~75+
            // Grid/table numbers: fontSize ~12, x varies
            return fontSize >= 14 && x >= 30 && x <= 70;
        });

        for (const item of candidates) {
            positions.push({
                num: parseInt(item.str.trim()),
                page: p,
                pdfY: item.transform[5],
                fontSize: item.transform[0],
                x: item.transform[4],
            });
        }

        // Look for copyright/footer markers and section headers on each page
        for (const item of textContent.items) {
            if (item.str.includes('©') || item.str.includes('\u00A9')) {
                const footerY = item.transform[5];
                const footerFontSize = item.transform[0];
                // Store the top of the footer box (Y + fontSize + margin) as the boundary
                // We want to crop ABOVE this, so store the pdfY that represents the top edge
                if (footerPositions[p] === undefined || footerY > footerPositions[p]) {
                    footerPositions[p] = footerY + footerFontSize + 8;
                }
            }
            // Detect "Pour départager" section header between Q24 and Q25
            if (item.str.includes('départager')) {
                const headerY = item.transform[5];
                const headerFontSize = item.transform[0];
                sectionHeaderPositions[p] = headerY + headerFontSize + 8;
            }
        }
    }

    positions.sort((a, b) => a.num - b.num);

    const seen = new Set();
    const filtered = positions.filter(p => {
        if (p.num > 26 || seen.has(p.num)) {
            return false;
        }
        seen.add(p.num);
        return true;
    });

    return { questions: filtered, footerPositions, sectionHeaderPositions };
}

main().catch(err => {
    console.log(JSON.stringify({ success: false, error: err.message }));
    process.exit(1);
});
