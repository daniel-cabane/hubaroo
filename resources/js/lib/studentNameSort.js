export function studentDisplayName(student) {
  return student?.pivot?.class_name ?? student?.name ?? '';
}

/**
 * Last name is the second word onward of the displayed full name.
 * "Alice MARTIN" sorts as "martin"; "Jean-Paul LA GLU DE COLLE" sorts as "la glu de colle".
 */
export function lastNameSortKey(name) {
  const words = String(name ?? '').trim().split(/\s+/).filter(Boolean);

  if (words.length < 2) {
    return (words[0] ?? '').toLocaleLowerCase('fr');
  }

  return words.slice(1).join(' ').toLocaleLowerCase('fr');
}

export function compareStudentsByLastName(a, b) {
  const nameA = studentDisplayName(a);
  const nameB = studentDisplayName(b);
  const byLastName = lastNameSortKey(nameA).localeCompare(lastNameSortKey(nameB), 'fr', { sensitivity: 'base' });

  if (byLastName !== 0) {
    return byLastName;
  }

  return nameA.localeCompare(nameB, 'fr', { sensitivity: 'base' });
}
