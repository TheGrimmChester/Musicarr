#!/bin/bash

echo "🎵 Alternative Favicon Generation for Musicarr"
echo "=============================================="
echo ""

# Check what tools are available
echo "🔍 Checking available tools..."

# Check for Node.js
if command -v node &> /dev/null; then
    echo "✅ Node.js found"
    NODE_AVAILABLE=true
else
    echo "❌ Node.js not found"
    NODE_AVAILABLE=false
fi

# Check for Python3
if command -v python3 &> /dev/null; then
    echo "✅ Python3 found"
    PYTHON_AVAILABLE=true
else
    echo "❌ Python3 not found"
    PYTHON_AVAILABLE=false
fi

# Check for npm packages
if [ -f "node_modules/.bin/sharp" ] || [ -f "node_modules/sharp/package.json" ]; then
    echo "✅ Sharp npm package found"
    SHARP_AVAILABLE=true
else
    echo "❌ Sharp npm package not found"
    SHARP_AVAILABLE=false
fi

echo ""

# Offer options
echo "🚀 Choose your favicon generation method:"
echo ""

if [ "$NODE_AVAILABLE" = true ] && [ "$SHARP_AVAILABLE" = true ]; then
    echo "1️⃣  Node.js + Sharp (Recommended - Best quality)"
fi

if [ "$PYTHON_AVAILABLE" = true ]; then
    echo "2️⃣  Python3 + CairoSVG (Good alternative)"
fi

echo "3️⃣  Online favicon generator (No local tools needed)"
echo "4️⃣  Manual conversion (Use external tools)"

echo ""
read -p "Enter your choice (1-4): " choice

case $choice in
    1)
        if [ "$NODE_AVAILABLE" = true ] && [ "$SHARP_AVAILABLE" = true ]; then
            echo ""
            echo "🚀 Using Node.js + Sharp..."
            node scripts/generate-favicon.js
        else
            echo "❌ Node.js + Sharp not available"
            exit 1
        fi
        ;;
    2)
        if [ "$PYTHON_AVAILABLE" = true ]; then
            echo ""
            echo "🐍 Using Python3 + CairoSVG..."
            echo "💡 Installing required packages..."
            pip3 install cairosvg pillow
            python3 scripts/generate-favicon.py
        else
            echo "❌ Python3 not available"
            exit 1
        fi
        ;;
    3)
        echo ""
        echo "🌐 Online favicon generator options:"
        echo ""
        echo "📱 Recommended online tools:"
        echo "   • https://favicon.io/favicon-converter/ (Best for ICO)"
        echo "   • https://realfavicongenerator.net/ (Most comprehensive)"
        echo "   • https://www.favicon-generator.org/ (Simple)"
        echo ""
        echo "📋 Steps:"
        echo "   1. Upload your public/favicon.svg file"
        echo "   2. Download the generated favicon package"
        echo "   3. Extract and place files in public/ directory"
        echo ""
        echo "💡 This method generates all formats including ICO!"
        ;;
    4)
        echo ""
        echo "🔧 Manual conversion options:"
        echo ""
        echo "📱 Desktop applications:"
        echo "   • GIMP (Free, cross-platform)"
        echo "   • Inkscape (Free, cross-platform)"
        echo "   • Photoshop (Paid, Windows/Mac)"
        echo ""
        echo "📋 Steps:"
        echo "   1. Open public/favicon.svg in your preferred editor"
        echo "   2. Export as PNG in different sizes (16x16, 32x32, 48x48)"
        echo "   3. Use online converter for ICO format"
        ;;
    *)
        echo "❌ Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "🎉 Favicon generation completed!"
echo "💡 Don't forget to clear your browser cache to see the new favicon!"
