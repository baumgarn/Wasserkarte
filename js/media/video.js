export function videoDuration(seconds) {
	if (!Number.isFinite(seconds) || seconds < 0) return '';
	return `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
}

export function videoPoster(file, signal) {
	return new Promise((resolve, reject) => {
		const video = document.createElement('video');
		const url = URL.createObjectURL(file);
		let settled = false;
		const finish = (error, poster) => {
			if (settled) return;
			settled = true;
			clearTimeout(timeout);
			signal?.removeEventListener('abort', abort);
			video.onloadeddata = video.onerror = video.onseeked = null;
			video.removeAttribute('src');
			video.load();
			URL.revokeObjectURL(url);
			error ? reject(error) : resolve(poster);
		};
		const abort = () => finish(new DOMException('Upload abgebrochen.', 'AbortError'));
		const timeout = setTimeout(() => finish(new Error('Videovorschau konnte nicht erstellt werden.')), 20000);
		const capture = () => {
			try {
				const canvas = document.createElement('canvas');
				const scale = Math.min(1, 300 / Math.max(video.videoWidth, video.videoHeight));
				canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
				canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
				canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
				const duration = video.duration;
				canvas.toBlob(blob => finish(blob ? null : new Error('Videovorschau konnte nicht erstellt werden.'), { poster: blob, duration }), 'image/webp', 0.8);
			} catch { finish(new Error('Videovorschau konnte nicht erstellt werden.')); }
		};
		video.muted = true;
		video.playsInline = true;
		video.preload = 'auto';
		video.onerror = () => finish(new Error('Dieses Video kann nicht gelesen werden. Bitte MP4 mit H.264 und AAC auswählen.'));
		video.onloadeddata = () => {
			video.onloadeddata = null;
			if (video.duration > 0.2) {
				video.onseeked = capture;
				video.currentTime = Math.min(1, video.duration / 2);
			} else capture();
		};
		signal?.addEventListener('abort', abort, { once: true });
		if (signal?.aborted) abort();
		else video.src = url;
	});
}
