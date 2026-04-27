## Partial Images

# $url to download pdf test papers (by $year and by $level - php syntax) : 
/*
* @param  int  $year  The year of the competition (2004-2025)
* @param  string  $level  The level letter (e, b, c, p, j, s)
*/
if ($year <= 2010) {
    $levels = [
        'e' => 'ecol', 'b' => 'benj', 'c' => 'cade', 'p' => '', 'j' => 'juni', 's' => 'etud',
    ];
    $url = "https://www.mathkang.org/pdf/{$levels[$level]}{$year}.pdf";
} else {
    $url = "https://www.mathkang.org/pdf/kangourou{$year}{$level}.pdf";
}

# List of images to regenerate

note that all file names follow the pattern {year}_{level}_q{question number}.png

This list contains question images with visibly clipped content from `public/questions`. I excluded weaker band-only edge cases that appeared fully visible during review.

- 2004_c_q25.png
- 2004_c_q6.png
- 2004_e_q16.png
- 2004_e_q24.png
- 2004_e_q6.png
- 2004_e_q8.png
- 2006_b_q15.png
- 2006_b_q25.png
- 2006_b_q26.png
- 2006_c_q25.png
- 2006_c_q26.png
- 2006_e_q15.png
- 2006_e_q16.png
- 2006_j_q25.png
- 2006_j_q26.png
- 2006_s_q18.png
- 2006_s_q25.png
- 2006_s_q26.png
- 2007_b_q25.png
- 2007_c_q25.png
- 2007_c_q26.png
- 2007_e_q25.png
- 2007_j_q25.png
- 2007_j_q26.png
- 2007_s_q25.png
- 2007_s_q26.png
- 2008_b_q15.png
- 2008_b_q25.png
- 2008_e_q25.png
- 2008_e_q26.png
- 2008_e_q5.png
- 2008_j_q25.png
- 2008_s_q25.png
- 2009_b_q25.png
- 2009_b_q26.png
- 2009_e_q11.png
- 2009_e_q5.png
- 2009_e_q6.png
- 2009_j_q2.png
- 2009_j_q25.png
- 2009_s_q16.png
- 2010_b_q10.png
- 2010_b_q2.png
- 2010_b_q25.png
- 2010_e_q16.png
- 2010_e_q21.png
- 2010_e_q25.png
- 2010_e_q4.png
- 2011_b_q10.png
- 2011_b_q23.png
- 2011_b_q25.png
- 2011_e_q16.png
- 2011_e_q25.png
- 2011_p_q16.png
- 2011_p_q25.png
- 2011_s_q25.png
- 2012_b_q25.png
- 2012_b_q26.png
- 2012_b_q5.png
- 2012_b_q8.png
- 2012_c_q13.png
- 2012_c_q25.png
- 2012_c_q3.png
- 2012_e_q10.png
- 2012_e_q15.png
- 2012_e_q16.png
- 2012_e_q18.png
- 2012_e_q25.png
- 2012_e_q26.png
- 2012_j_q21.png
- 2012_p_q25.png
- 2012_p_q3.png
- 2012_s_q13.png
- 2012_s_q15.png
- 2012_s_q16.png
- 2012_s_q17.png
- 2012_s_q19.png
- 2012_s_q23.png
- 2012_s_q25.png
- 2012_s_q7.png
- 2013_b_q14.png
- 2013_e_q12.png
- 2013_e_q25.png
- 2013_e_q9.png
- 2013_p_q25.png
- 2013_s_q25.png
- 2014_b_q10.png
- 2014_e_q11.png
- 2014_e_q16.png
- 2014_e_q2.png
- 2014_e_q25.png
- 2014_j_q25.png
- 2014_p_q25.png
- 2015_b_q14.png
- 2015_c_q26.png
- 2015_e_q12.png
- 2015_e_q15.png
- 2015_e_q16.png
- 2015_e_q25.png
- 2015_e_q26.png
- 2015_e_q6.png
- 2015_j_q22.png
- 2015_j_q25.png
- 2015_p_q25.png
- 2016_b_q25.png
- 2016_c_q6.png
- 2016_e_q16.png
- 2016_e_q24.png
- 2016_e_q25.png
- 2017_b_q25.png
- 2017_b_q4.png
- 2017_e_q16.png
- 2017_e_q25.png
- 2017_e_q9.png
- 2017_p_q25.png
- 2017_p_q3.png
- 2018_b_q25.png
- 2018_c_q25.png
- 2018_c_q7.png
- 2018_e_q25.png
- 2018_p_q25.png
- 2018_p_q8.png
- 2019_b_q15.png
- 2019_b_q22.png
- 2019_b_q25.png
- 2019_b_q8.png
- 2019_c_q25.png
- 2019_e_q20.png
- 2019_e_q25.png
- 2019_p_q25.png
- 2020_b_q25.png
- 2020_c_q25.png
- 2020_e_q25.png
- 2020_e_q9.png
- 2020_j_q3.png
- 2020_p_q3.png
- 2020_s_q25.png
- 2021_b_q10.png
- 2021_b_q24.png
- 2021_b_q25.png
- 2021_c_q2.png
- 2021_c_q25.png
- 2021_e_q16.png
- 2021_e_q25.png
- 2021_j_q25.png
- 2021_p_q13.png
- 2021_s_q25.png
- 2022_b_q12.png
- 2022_b_q25.png
- 2022_c_q15.png
- 2022_c_q25.png
- 2022_c_q26.png
- 2022_e_q10.png
- 2022_e_q25.png
- 2022_j_q18.png
- 2022_p_q10.png
- 2022_p_q15.png
- 2022_s_q25.png
- 2022_s_q4.png
- 2022_s_q5.png
- 2023_b_q10.png
- 2023_b_q25.png
- 2023_c_q25.png
- 2023_e_q22.png
- 2023_e_q25.png
- 2023_j_q25.png
- 2023_p_q15.png
- 2023_p_q21.png
- 2023_p_q25.png
- 2023_s_q25.png
- 2024_b_q14.png
- 2024_b_q21.png
- 2024_b_q23.png
- 2024_b_q24.png
- 2024_b_q25.png
- 2024_c_q25.png
- 2024_c_q9.png
- 2024_e_q25.png
- 2024_j_q25.png
- 2024_p_q14.png
- 2024_p_q23.png
- 2024_p_q24.png
- 2024_p_q25.png
- 2024_s_q4.png
- 2025_b_q25.png
- 2025_b_q5.png
- 2025_c_q25.png
- 2025_c_q3.png
- 2025_c_q8.png
- 2025_e_q25.png
- 2025_e_q5.png
- 2025_j_q25.png
- 2025_p_q12.png
- 2025_p_q25.png
- 2025_p_q5.png
- 2025_s_q16.png
- 2025_s_q25.png