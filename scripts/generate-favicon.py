#!/usr/bin/env python3

import os
import sys
from pathlib import Path

try:
    import cairosvg
    from PIL import Image
    import io
except ImportError as e:
    print(f"❌ Required libraries not found: {e}")
    print("💡 Install them with: pip install cairosvg pillow")
    sys.exit(1)

def generate_favicons():
    print("🎵 Generating favicon files for Musicarr...")
    
    # Get script directory and project paths
    script_dir = Path(__file__).parent
    svg_path = script_dir.parent / "public" / "favicon.svg"
    output_dir = script_dir.parent / "public"
    
    # Check if SVG exists
    if not svg_path.exists():
        print(f"❌ SVG favicon not found at: {svg_path}")
        sys.exit(1)
    
    # Create output directory if it doesn't exist
    output_dir.mkdir(exist_ok=True)
    
    try:
        # Read SVG content
        with open(svg_path, 'r', encoding='utf-8') as f:
            svg_content = f.read()
        
        # Generate PNG favicon (32x32)
        print("📱 Generating PNG favicon (32x32)...")
        png_data = cairosvg.svg2png(bytestring=svg_content, output_width=32, output_height=32)
        with open(output_dir / "favicon.png", "wb") as f:
            f.write(png_data)
        
        # Generate PNG favicon (16x16)
        print("📱 Generating PNG favicon (16x16)...")
        png_data = cairosvg.svg2png(bytestring=svg_content, output_width=16, output_height=16)
        with open(output_dir / "favicon-16.png", "wb") as f:
            f.write(png_data)
        
        # Generate PNG favicon (48x48)
        print("📱 Generating PNG favicon (48x48)...")
        png_data = cairosvg.svg2png(bytestring=svg_content, output_width=48, output_height=48)
        with open(output_dir / "favicon-48.png", "wb") as f:
            f.write(png_data)
        
        # Generate PNG favicon (192x192) for PWA
        print("📱 Generating PNG favicon (192x192)...")
        png_data = cairosvg.svg2png(bytestring=svg_content, output_width=192, output_height=192)
        with open(output_dir / "favicon-192.png", "wb") as f:
            f.write(png_data)
        
        print("✅ PNG favicon files generated successfully!")
        print("   - public/favicon.png (32x32)")
        print("   - public/favicon-16.png (16x16)")
        print("   - public/favicon-48.png (48x48)")
        print("   - public/favicon-192.png (192x192)")
        
        print("")
        print("💡 Note: For ICO format, you can use online converters like:")
        print("   - https://favicon.io/favicon-converter/")
        print("   - https://www.favicon-generator.org/")
        print("   - https://realfavicongenerator.net/")
        print("")
        print("🎉 Your Musicarr favicon PNG files are ready!")
        
    except Exception as e:
        print(f"❌ Error generating favicons: {e}")
        sys.exit(1)

if __name__ == "__main__":
    generate_favicons()
