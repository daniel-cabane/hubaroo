import assert from 'node:assert/strict';
import test from 'node:test';
import { compareStudentsByLastName, lastNameSortKey } from '../../resources/js/lib/studentNameSort.js';

function student(className, name = 'Account Name') {
  return { name, pivot: { class_name: className } };
}

test('last name sort key starts at the second word', () => {
  assert.equal(lastNameSortKey('Alice MARTIN'), 'martin');
  assert.equal(lastNameSortKey('Jean-Paul LA GLU DE COLLE'), 'la glu de colle');
  assert.equal(lastNameSortKey('  Bob   DUPONT  '), 'dupont');
  assert.equal(lastNameSortKey('Cherif'), 'cherif');
  assert.equal(lastNameSortKey(''), '');
});

test('course name column orders students by last name, then full name', () => {
  const students = [
    student('Zoe MARTIN'),
    student('Alice ZORRO'),
    student('Bob DUPONT'),
    student('Alice MARTIN'),
    student('Jean-Paul LA GLU DE COLLE'),
    student('Pierre LAMBERT'),
    student('Marie ÉVRARD'),
  ];

  const ordered = [...students].sort(compareStudentsByLastName).map((row) => row.pivot.class_name);

  assert.deepEqual(ordered, [
    'Bob DUPONT',
    'Marie ÉVRARD',
    'Jean-Paul LA GLU DE COLLE',
    'Pierre LAMBERT',
    'Alice MARTIN',
    'Zoe MARTIN',
    'Alice ZORRO',
  ]);
});

test('class name is used ahead of the account name', () => {
  const byClassName = compareStudentsByLastName(
    student('Zoe MARTIN', 'Alice DUPONT'),
    student('Bob ARNAUD', 'Zoe ZORRO'),
  );

  assert.ok(byClassName > 0);
});

test('descending name sort reverses last-name order', () => {
  const students = [student('Alice MARTIN'), student('Bob DUPONT')];
  const ordered = [...students]
    .sort((a, b) => compareStudentsByLastName(a, b) * -1)
    .map((row) => row.pivot.class_name);

  assert.deepEqual(ordered, ['Alice MARTIN', 'Bob DUPONT']);
});
