#!/usr/bin/env node

require("dotenv").config();

const ftp = require("basic-ftp");
const fs = require("fs");
const path = require("path");

const CACHE_FILES = [
	"alltelemetry.json",
	"alltelemetry.json.gz",
	"devices.json",
	"devices.json.gz",
	"posts.json"
];

const MEDIA_ID = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/;

function mediaDownloads(index, posts) {
	if (index?.version !== 1 || !Array.isArray(index.media) || !Array.isArray(posts?.posts)) {
		throw new Error('Invalid production media or posts index');
	}
	const productionPosts = new Map(posts.posts.filter(post => !post.environment).map(post => [post.id, post]));
	const media = index.media.filter(item => {
		const post = productionPosts.get(item.postId);
		return item.environment === 'production' && post?.deviceId === item.deviceId && post.mediaIds?.includes(item.id);
	});
	const files = [];
	for (const item of media) {
		if (!MEDIA_ID.test(item.id) || item.type !== 'image') throw new Error('Invalid production media entry');
		for (const [variant, directory] of [['display', 'display'], ['thumbnail', 'thumbnails']]) {
			const filename = `${directory}/${item.id}.webp`;
			if (item.variants?.[variant]?.path !== filename) throw new Error('Invalid media file path');
			files.push(filename);
		}
	}
	return { index: { ...index, media }, files };
}

async function pullMedia(client, remoteDir, mediaDir, posts) {
	fs.mkdirSync(mediaDir, { recursive: true });
	const protectionFile = path.join(mediaDir, '.htaccess');
	if (!fs.existsSync(protectionFile)) fs.writeFileSync(protectionFile, 'Options -Indexes\nRequire all denied\n');
	// Eigener temporärer Ordner: bei einem Abbruch bleiben Index und lokale Uploads erhalten.
	const staging = fs.mkdtempSync(path.join(mediaDir, '.pull-'));
	try {
		const remoteIndex = path.posix.join(remoteDir, 'api/storage/media.json');
		try {
			await client.downloadTo(path.join(staging, 'media.json'), remoteIndex);
		} catch (error) {
			if (error.code === 550) {
				console.log('Production media index is not available yet; media download skipped.');
				return;
			}
			throw error;
		}
		const downloaded = JSON.parse(fs.readFileSync(path.join(staging, 'media.json'), 'utf8'));
		const { index, files } = mediaDownloads(downloaded, posts);
		for (const filename of files) {
			const target = path.join(staging, filename);
			fs.mkdirSync(path.dirname(target), { recursive: true });
			console.log(`Downloading storage/${filename}`);
			await client.downloadTo(target, path.posix.join(remoteDir, 'api/storage', filename));
		}
		for (const filename of files) {
			const target = path.join(mediaDir, filename);
			fs.mkdirSync(path.dirname(target), { recursive: true });
			fs.renameSync(path.join(staging, filename), target);
		}
		fs.writeFileSync(path.join(staging, 'media.json'), JSON.stringify(index, null, 2));
		fs.renameSync(path.join(staging, 'media.json'), path.join(mediaDir, 'media.json'));
		console.log(`Media download complete (${index.media.length} images).`);
	} finally {
		fs.rmSync(staging, { recursive: true, force: true });
	}
}

function getFtpConfig(env = process.env) {
	const { FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_REMOTE_DIR } = env;
	if (!FTP_HOST || !FTP_USER || !FTP_PASSWORD || !FTP_REMOTE_DIR) {
		throw new Error("Missing FTP config in .env file");
	}

	return { FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_REMOTE_DIR };
}

function remoteCachePath(remoteDir, filename) {
	return path.posix.join(remoteDir, "api", "cache", filename);
}

async function pullData() {
	const client = new ftp.Client();
	const cacheDir = path.join(__dirname, "..", "api", "cache");
	const storageDir = path.join(__dirname, "..", "api", "storage");

	client.ftp.verbose = true;

	try {
		const config = getFtpConfig();
		fs.mkdirSync(cacheDir, { recursive: true });

		await client.access({
			host: config.FTP_HOST,
			user: config.FTP_USER,
			password: config.FTP_PASSWORD,
			secure: true,
			secureOptions: {
				rejectUnauthorized: false
			}
		});
		client.ftp.keepAlive = 10000;

		for (const filename of CACHE_FILES) {
			const remotePath = remoteCachePath(config.FTP_REMOTE_DIR, filename);
			console.log(`Downloading ${remotePath}`);
			const target = path.join(cacheDir, filename);
			await client.downloadTo(`${target}.download`, remotePath);
			fs.renameSync(`${target}.download`, target);
		}
		const posts = JSON.parse(fs.readFileSync(path.join(cacheDir, 'posts.json'), 'utf8'));
		await pullMedia(client, config.FTP_REMOTE_DIR, storageDir, posts);

		console.log("Cache download complete.");
	} catch (error) {
		console.error("Cache download failed:", error.message);
		process.exitCode = 1;
	} finally {
		client.close();
	}
}

if (require.main === module) {
	pullData();
}

module.exports = {
	CACHE_FILES,
	getFtpConfig,
	remoteCachePath,
	mediaDownloads,
	pullMedia,
	pullData
};
