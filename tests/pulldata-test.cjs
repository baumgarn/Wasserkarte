const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { mediaDownloads, pullMedia } = require('../scripts/pulldata.cjs');

const id = '11111111-1111-4111-8111-111111111111';
const localId = '22222222-2222-4222-8222-222222222222';
const item = { id, type: 'image', environment: 'production', postId: 'post', deviceId: 'device', variants: { display: { path: `display/${id}.webp` }, thumbnail: { path: `thumbnails/${id}.webp` } } };
const posts = { posts: [{ id: 'post', deviceId: 'device', mediaIds: [id] }] };
const source = { version: 1, media: [item, { ...item, id: localId, environment: 'local' }, { ...item, id: localId, postId: 'unpublished' }] };
assert.equal(mediaDownloads(source, posts).index.media.length, 1);
assert.equal(mediaDownloads(source, posts).files.length, 2);
assert.throws(() => mediaDownloads({ version: 1, media: [{ ...item, variants: { display: { path: '../secret' } } }] }, posts));

async function main() {
	const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'wasserkarte-pulldata-test-'));
	try {
		const mediaDir = path.join(directory, 'storage');
		fs.mkdirSync(path.join(mediaDir, 'local'), { recursive: true });
		fs.writeFileSync(path.join(mediaDir, 'local', 'media.json'), 'local sentinel');
		const requested = [];
		const client = { async downloadTo(target, remote) { requested.push(remote); fs.writeFileSync(target, remote.endsWith('media.json') ? JSON.stringify(source) : 'fixture image'); } };
		await pullMedia(client, '/production', mediaDir, posts);
		assert.deepEqual(requested, ['/production/api/storage/media.json', `/production/api/storage/display/${id}.webp`, `/production/api/storage/thumbnails/${id}.webp`]);
		assert.ok(!fs.existsSync(path.join(directory, 'cache')));
		assert.equal(JSON.parse(fs.readFileSync(path.join(mediaDir, 'media.json'))).media.length, 1);
		assert.equal(fs.readFileSync(path.join(mediaDir, 'local', 'media.json'), 'utf8'), 'local sentinel');
		const before = fs.readFileSync(path.join(mediaDir, 'media.json'), 'utf8');
		const broken = { async downloadTo(target, remote) { if (remote.endsWith('media.json')) fs.writeFileSync(target, JSON.stringify(source)); else throw new Error('interrupted download'); } };
		await assert.rejects(pullMedia(broken, '/production', mediaDir, posts));
		assert.equal(fs.readFileSync(path.join(mediaDir, 'media.json'), 'utf8'), before);
		assert.equal(fs.readFileSync(path.join(mediaDir, 'local', 'media.json'), 'utf8'), 'local sentinel');
		assert.deepEqual(fs.readdirSync(mediaDir).filter(name => name.startsWith('.pull-')), []);
		console.log('OK: production filtering, path validation, both variants, local preservation and interrupted downloads');
	} finally { fs.rmSync(directory, { recursive: true, force: true }); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
