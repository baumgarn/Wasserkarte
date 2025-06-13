// upload-ftp.js
require("dotenv").config();
const ftp = require("basic-ftp");
const path = require("path");
const fs = require("fs");

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

		await client.uploadFromDir(localDist);
		console.log("✅ Upload complete.");
		
	} catch (err) {
		console.error("❌ FTP upload failed:", err);
	}

	client.close();
}

upload();