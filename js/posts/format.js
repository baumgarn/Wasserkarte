export function formatPostDate(timestamp) {
	const date = new Date(Number(timestamp));
	if (Number.isNaN(date.getTime())) return '';
	const month = date.toLocaleDateString('de-DE', { month: 'short' }).replace(/\.$/, '');
	return `${date.getDate()}. ${month} ${date.getFullYear()}`;
}
