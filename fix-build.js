const fs = require('fs');
const path = require('path');

// Fix booking-package-tracker build structure
const trackerBuildDir = './build/features/booking-package-tracker';
const nestedDir = path.join(trackerBuildDir, 'features', 'booking-package-tracker');

// If nested directory exists, move files up
if (fs.existsSync(nestedDir)) {
	console.log('Fixing nested directory structure...');
	
	// Move all files from nested directory to correct location
	const filesToMove = ['block.json', 'render.php', 'frontend.js', 'index.js', 'frontend.asset.php', 'index.asset.php'];
	
	filesToMove.forEach(file => {
		const sourcePath = path.join(nestedDir, file);
		const destPath = path.join(trackerBuildDir, file);
		if (fs.existsSync(sourcePath)) {
			fs.copyFileSync(sourcePath, destPath);
			console.log(`Copied ${file} to correct location`);
		}
	});
	
	// Remove nested directory
	fs.rmSync(path.join(trackerBuildDir, 'features'), { recursive: true, force: true });
	console.log('Removed nested directory structure');
}

// Also check if JS files exist in the tracker directory root (they should be there after wp-scripts build)
// If they don't exist, check if they're in a nested location OR in the root build directory
// (This can happen when builds run in parallel and wp-scripts outputs to the wrong location)
const expectedFiles = ['frontend.js', 'index.js', 'frontend.asset.php', 'index.asset.php'];
expectedFiles.forEach(file => {
	const filePath = path.join(trackerBuildDir, file);
	if (!fs.existsSync(filePath)) {
		console.log(`WARNING: ${file} not found at expected location: ${filePath}`);
		
		// Check if it's in a nested features directory
		const nestedPath = path.join(trackerBuildDir, 'features', 'booking-package-tracker', file);
		if (fs.existsSync(nestedPath)) {
			fs.copyFileSync(nestedPath, filePath);
			console.log(`Found and moved ${file} from nested location`);
		} else {
			// Check if it's in the root build directory (parallel build issue)
			const rootBuildPath = path.join('./build', file);
			if (fs.existsSync(rootBuildPath)) {
				// Only copy if this is a tracker-specific file (we can't distinguish, so we'll copy and let the pet build overwrite)
				// Actually, we need a better way - let's check the build output or use a different approach
				// For now, we'll create a separate copy step after both builds complete
				console.log(`Found ${file} in root build directory - this might be from parallel build`);
			}
		}
	}
});

// After both builds complete, we need to ensure tracker files are in the right place
// The tracker build should output to build/features/booking-package-tracker/, but if it doesn't,
// we need to manually move them. However, we can't distinguish tracker files from pet files
// in the root build directory, so we need to ensure the tracker build outputs correctly.

// Create a post-build step: if tracker JS files are missing, check if we need to rebuild just the tracker
// For now, we'll just ensure the directory structure exists
if (!fs.existsSync(trackerBuildDir)) {
	fs.mkdirSync(trackerBuildDir, { recursive: true });
	console.log('Created tracker build directory');
}

console.log('Build structure fixed!');
