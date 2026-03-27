// upload-ftp.js
require("dotenv").config();
const ftp = require("basic-ftp");
const path = require("path");
const fs = require("fs");

const UPLOAD_SKIP_PATHS = [
	"api/cache",
];

function normalizeRelativePath(relativePath) {
	return relativePath.split(path.sep).join("/");
}

function shouldSkipUpload(relativePath) {
	const normalizedPath = normalizeRelativePath(relativePath);
	if (!normalizedPath || normalizedPath === ".") {
		return false;
	}

	if (normalizedPath === ".DS_Store" || normalizedPath.endsWith("/.DS_Store")) {
		return true;
	}

	return UPLOAD_SKIP_PATHS.some(skipPath =>
		normalizedPath === skipPath || normalizedPath.startsWith(`${skipPath}/`)
	);
}

async function uploadDirectory(client, localDir, remoteDir, rootDir = localDir) {
	const entries = fs.readdirSync(localDir, { withFileTypes: true });

	for (const entry of entries) {
		const localPath = path.join(localDir, entry.name);
		const relativePath = path.relative(rootDir, localPath);

		if (shouldSkipUpload(relativePath)) {
			console.log(`Skipping upload: ${normalizeRelativePath(relativePath)}`);
			continue;
		}

		const remotePath = path.posix.join(remoteDir, entry.name);

		if (entry.isDirectory()) {
			await client.ensureDir(remotePath);
			await uploadDirectory(client, localPath, remotePath, rootDir);
			continue;
		}

		if (entry.isFile()) {
			await client.uploadFrom(localPath, remotePath);
		}
	}
}

async function upload() {
	const client = new ftp.Client();
	client.ftp.verbose = true;

	const {
		FTP_HOST,
		FTP_USER,
		FTP_PASSWORD,
		FTP_REMOTE_DIR
	} = process.env;

	if (!FTP_HOST || !FTP_USER || !FTP_PASSWORD || !FTP_REMOTE_DIR) {
		console.error("Missing FTP config in .env file");
		process.exit(1);
	}

	const localDist = path.join(__dirname, "dist");
	if (!fs.existsSync(localDist)) {
		console.error("Missing local 'dist' folder. Run build first.");
		process.exit(1);
	}

	try {
		await client.access({
			host: FTP_HOST,
			user: FTP_USER,
			password: FTP_PASSWORD,
			secure: true,
			secureOptions: {
				rejectUnauthorized: false
			}
		});
		client.ftp.verbose = true;
		client.ftp.keepAlive = 10000;

			console.log(`Navigating to: ${FTP_REMOTE_DIR}`);
			await client.ensureDir(FTP_REMOTE_DIR);
			await client.cd(FTP_REMOTE_DIR);

		// const files = await client.list();
		// const existingFiles = files.map(f => f.name);
		// const localFiles = fs.readdirSync(localDist);

		// Only remove files that exist in the local build (avoids deleting unrelated files)
		// for (const file of localFiles) {
		// 	if (existingFiles.includes(file)) {
		// 		console.log(`Deleting remote file: ${file}`);
		// 		await client.remove(file);
		// 	}
		// }

			await uploadDirectory(client, localDist, FTP_REMOTE_DIR);
			console.log("✅ Upload complete.");
		
	} catch (err) {
		console.error("❌ FTP upload failed:", err);
	}

	client.close();
}

upload();
