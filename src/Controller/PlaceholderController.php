<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/placeholder')]
class PlaceholderController extends AbstractController
{
    #[Route('/artist/{size}/{text}', name: 'placeholder_artist', requirements: ['size' => '\d+x\d+'])]
    public function artistPlaceholder(string $size = '50x50', string $text = 'Artist'): Response
    {
        [$width, $height] = explode('x', $size);

        $svg = $this->generateArtistSVG((int) $width, (int) $height, $text);

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000', // Cache 1 an
        ]);
    }

    #[Route('/album/{size}/{text}', name: 'placeholder_album', requirements: ['size' => '\d+x\d+'])]
    public function albumPlaceholder(string $size = '200x200', string $text = 'Album'): Response
    {
        [$width, $height] = explode('x', $size);

        $svg = $this->generateAlbumSVG((int) $width, (int) $height, $text);

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000', // Cache 1 an
        ]);
    }

    #[Route('/generic/{size}/{text}', name: 'placeholder_generic', requirements: ['size' => '\d+x\d+'])]
    public function genericPlaceholder(string $size = '100x100', string $text = 'Image'): Response
    {
        [$width, $height] = explode('x', $size);

        $svg = $this->generateGenericSVG((int) $width, (int) $height, $text);

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000', // Cache 1 an
        ]);
    }

    private function generateArtistSVG(int $width, int $height, string $text): string
    {
        $fontSize = min($width, $height) / 4;
        $iconSize = min($width, $height) / 3;
        $widthHalf = $width / 2;
        $heightHalf = $height / 2;
        $radius = $widthHalf - 5;
        $iconSizeHalf = $iconSize / 2;
        $iconSizeThird = $iconSize / 1.5;
        $heightPlus15 = $height + 15;
        $heightHalfMinus5 = $heightHalf - 5;
        $heightHalfPlus10 = $heightHalf + 10;

        return <<<SVG
        <svg width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="artistGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                </linearGradient>
                <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="2" dy="2" stdDeviation="3" flood-color="rgba(0,0,0,0.3)"/>
                </filter>
            </defs>
            <circle cx="{$widthHalf}" cy="{$heightHalf}" r="{$radius}" fill="url(#artistGradient)" filter="url(#shadow)"/>
            <circle cx="{$widthHalf}" cy="{$heightHalfMinus5}" r="{$iconSizeHalf}" fill="white" opacity="0.9"/>
            <circle cx="{$widthHalf}" cy="{$heightHalfPlus10}" r="{$iconSizeThird}" fill="white" opacity="0.9"/>
            <text x="{$widthHalf}" y="{$heightPlus15}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$fontSize}" fill="white" font-weight="bold">{$text}</text>
        </svg>
        SVG;
    }

    private function generateAlbumSVG(int $width, int $height, string $text): string
    {
        $fontSize = min($width, $height) / 8;
        $iconSize = min($width, $height) / 4;
        $widthHalf = $width / 2;
        $heightHalf = $height / 2;
        $widthMinus10 = $width - 10;
        $heightMinus10 = $height - 10;
        $iconSizeHalf = $iconSize / 2;
        $iconSizeQuarter = $iconSize / 4;
        $heightMinus10Text = $height - 10;

        return <<<SVG
        <svg width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="albumGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#f093fb;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#f5576c;stop-opacity:1" />
                </linearGradient>
                <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="2" dy="2" stdDeviation="3" flood-color="rgba(0,0,0,0.3)"/>
                </filter>
            </defs>
            <rect x="5" y="5" width="{$widthMinus10}" height="{$heightMinus10}" rx="10" fill="url(#albumGradient)" filter="url(#shadow)"/>
            <circle cx="{$widthHalf}" cy="{$heightHalf}" r="{$iconSize}" fill="white" opacity="0.9"/>
            <circle cx="{$widthHalf}" cy="{$heightHalf}" r="{$iconSizeHalf}" fill="none" stroke="url(#albumGradient)" stroke-width="3"/>
            <circle cx="{$widthHalf}" cy="{$heightHalf}" r="{$iconSizeQuarter}" fill="url(#albumGradient)"/>
            <text x="{$widthHalf}" y="{$heightMinus10Text}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$fontSize}" fill="white" font-weight="bold">{$text}</text>
        </svg>
        SVG;
    }

    private function generateGenericSVG(int $width, int $height, string $text): string
    {
        $fontSize = min($width, $height) / 6;
        $widthMinus10 = $width - 10;
        $heightMinus10 = $height - 10;
        $widthHalf = $width / 2;
        $heightHalf = $height / 2;
        $heightHalfPlus5 = $heightHalf + 5;

        return <<<SVG
        <svg width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="genericGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#4facfe;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#00f2fe;stop-opacity:1" />
                </linearGradient>
                <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="2" dy="2" stdDeviation="3" flood-color="rgba(0,0,0,0.3)"/>
                </filter>
            </defs>
            <rect x="5" y="5" width="{$widthMinus10}" height="{$heightMinus10}" rx="8" fill="url(#genericGradient)" filter="url(#shadow)"/>
            <text x="{$widthHalf}" y="{$heightHalfPlus5}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$fontSize}" fill="white" font-weight="bold">{$text}</text>
        </svg>
        SVG;
    }
}
