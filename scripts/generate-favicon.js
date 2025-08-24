#!/usr/bin/env node

const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

console.log('🎵 Generating favicon files for Musicarr...');

async function generateFavicons() {
    try {
        const svgPath = path.join(__dirname, '../public/favicon.svg');
        const outputDir = path.join(__dirname, '../public');
        
        // Check if SVG exists
        if (!fs.existsSync(svgPath)) {
            console.error('❌ SVG favicon not found at:', svgPath);
            process.exit(1);
        }
        
        // Read SVG content
        const svgContent = fs.readFileSync(svgPath, 'utf8');
        
        // Generate PNG favicon (32x32)
        console.log('📱 Generating PNG favicon (32x32)...');
        await sharp(Buffer.from(svgContent))
            .resize(32, 32)
            .png()
            .toFile(path.join(outputDir, 'favicon.png'));
        
        // Generate PNG favicon (16x16)
        console.log('📱 Generating PNG favicon (16x16)...');
        await sharp(Buffer.from(svgContent))
            .resize(16, 16)
            .png()
            .toFile(path.join(outputDir, 'favicon-16.png'));
        
        // Generate PNG favicon (48x48)
        console.log('📱 Generating PNG favicon (48x48)...');
        await sharp(Buffer.from(svgContent))
            .resize(48, 48)
            .png()
            .toFile(path.join(outputDir, 'favicon-48.png'));
        
        // Generate PNG favicon (192x192) for PWA
        console.log('📱 Generating PNG favicon (192x192)...');
        await sharp(Buffer.from(svgContent))
            .resize(192, 192)
            .png()
            .toFile(path.join(outputDir, 'favicon-192.png'));
        
        // Generate PNG favicon (512x512) for PWA
        console.log('📱 Generating PNG favicon (512x512)...');
        await sharp(Buffer.from(svgContent))
            .resize(512, 512)
            .png()
            .toFile(path.join(outputDir, 'favicon-512.png'));
        
        console.log('✅ PNG favicon files generated successfully!');
        console.log('   - public/favicon.png (32x32)');
        console.log('   - public/favicon-16.png (16x16)');
        console.log('   - public/favicon-48.png (48x48)');
        console.log('   - public/favicon-192.png (192x192)');
        console.log('   - public/favicon-512.png (512x512)');
        
        console.log('');
        console.log('💡 Note: For ICO format, you can use online converters like:');
        console.log('   - https://favicon.io/favicon-converter/');
        console.log('   - https://www.favicon-generator.org/');
        console.log('   - https://realfavicongenerator.net/');
        console.log('');
        console.log('🎉 Your Musicarr favicon PNG files are ready!');
        
    } catch (error) {
        console.error('❌ Error generating favicons:', error.message);
        process.exit(1);
    }
}

// Run the generation
generateFavicons();
