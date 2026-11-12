<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Enums\BubbleType;
use App\Models\BdConversation;
use App\Support\LocaleFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Export PDF d'une conversation en BD (P20-09, PRD P0-8) : A4 portrait, par `barryvdh/laravel-dompdf` (déjà installé,
 * ADR-23 : pur PHP, aucun binaire externe ni navigateur sans tête, adapté à l'hébergement mutualisé cPanel).
 *
 * Chaque case : illustration (WebP converti en JPEG par GD en mémoire, sinon repli rose pâle avec la description de la
 * scène) + **formes des bulles en vectoriel** (le SVG de BubbleSvg sans ses textes, dessiné par php-svg-lib) + **texte
 * des bulles en vrai texte PDF**, sélectionnable et lisible par un lecteur d'écran, posé exactement aux coordonnées
 * calculées par BubbleGeometry (mêmes lignes, même taille relative que le rendu web). Puis : titre, résumé, auteur·rice
 * (création d'un·e membre : « Anonyme » ou pseudonyme), message clé, questions d'animation (modèles), transcription de
 * chaque case, mention de la source et de l'adresse sur chaque page.
 *
 * Polices embarquées (sous-ensembles) : Liberation Sans (texte, licence SIL OFL 1.1) et Montserrat Bold (titres, cris,
 * cartouches, OFL 1.1), resources/fonts ; métriques mises en cache dans storage/fonts (dossier inscriptible, ignoré par git).
 * Aucune ressource distante (`isRemoteEnabled` désactivé) : une image hébergée ailleurs est remplacée par le repli.
 */
final class ConversationPdf
{
    /** Largeur d'une case dans la page (mm) ; hauteur au format 3:2. */
    public const PANEL_MM = 170.0;

    /** Largeur maximale (px) de l'illustration intégrée : 300 dpi environ sur 170 mm ne sont pas utiles à l'écran. */
    private const IMAGE_MAX_PX = 1200;

    public function __construct(private readonly ConversationsBdCatalog $catalog) {}

    public function render(BdConversation $conversation): string
    {
        $fontDir = storage_path('fonts');
        File::ensureDirectoryExists($fontDir);

        $conversation->loadMissing(['panels.scene', 'panels.bubbles', 'author:id,pseudo']);
        $names = $this->catalog->names();
        $panels = [];

        foreach ($this->catalog->panels($conversation) as $index => $panel) {
            $model = $conversation->panels->values()->get($index);
            $panels[] = [
                'n' => $panel['n'],
                'alt' => $panel['alt'],
                'image' => $model !== null ? $this->imageData($model->scene?->image_path) : null,
                'shapes' => $this->shapes($panel['bubbles']),
                'texts' => $this->texts($panel['bubbles']),
                'transcript' => array_column(BubbleTranscript::lines($panel['bubbles'], $names), 'text'),
            ];
        }

        $url = $conversation->isPublished() ? route('conversations-bd.show', ['slug' => $conversation->slug]) : route('conversations-bd.index');

        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Liberation Sans',
            'fontDir' => $fontDir,
            'fontCache' => $fontDir,
            'chroot' => [base_path()],
            'dpi' => 96,
        ])->setPaper('a4', 'portrait')->loadView('pdf.conversation-bd', [
            'conversation' => $conversation,
            'panels' => $panels,
            'count' => count($panels),
            'panelMm' => self::PANEL_MM,
            'author' => $conversation->authorLabel(),
            'questions' => $conversation->questions(),
            'url' => $url,
            'published' => $conversation->isPublished(),
            'date' => LocaleFormatter::make()->longDate(now()),
            'fonts' => [
                'regular' => resource_path('fonts/liberation/LiberationSans-Regular.ttf'),
                'bold' => resource_path('fonts/liberation/LiberationSans-Bold.ttf'),
                'italic' => resource_path('fonts/liberation/LiberationSans-Italic.ttf'),
                'strong' => resource_path('fonts/montserrat-ttf/Montserrat-Bold.ttf'),
            ],
        ])->output();
    }

    public static function filename(BdConversation $conversation): string
    {
        return 'conversation-bd-'.(Str::slug((string) $conversation->title) ?: 'xomiton').'.pdf';
    }

    /**
     * Formes des bulles (contours, fonds, queues) en SVG, sans les textes (posés en vrai texte par la vue).
     *
     * @param  list<array<string, mixed>>  $bubbles
     */
    public function shapes(array $bubbles): string
    {
        $svg = (string) preg_replace('~<text\b.*?</text>~s', '', BubbleSvg::render($bubbles));
        $svg = str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" width="'.BubbleGeometry::WIDTH.'" height="'.BubbleGeometry::HEIGHT.'" ', $svg);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Lignes de texte de chaque bulle, en mm dans la case : boîte centrée sur la bulle, une ligne par entrée.
     *
     * @param  list<array<string, mixed>>  $bubbles
     * @return list<array{left: float, top: float, width: float, line: float, size: float, family: string, bold: bool, italic: bool, html: string}>
     */
    public function texts(array $bubbles): array
    {
        $scale = self::PANEL_MM / BubbleGeometry::WIDTH;
        $out = [];

        foreach ($bubbles as $bubble) {
            $l = BubbleGeometry::layout($bubble);

            if ($l['lines'] === []) {
                continue;
            }

            $type = $l['type'];
            $strong = $type === BubbleType::Shout || $type === BubbleType::Caption;
            $lineHeight = $l['font'] * BubbleGeometry::LINE_HEIGHT;
            $top = $l['cy'] - $l['textH'] / 2;
            // Boîte un peu plus large que le texte (le rendu web centre chaque ligne sur cx) : jamais de retour à la ligne.
            // Boîte centrée sur la bulle, gardée dans la case (une bulle au bord ne pousse pas son texte dehors).
            $width = min(max($l['textW'] * 1.4, $l['w']), 2 * max(1.0, $l['cx']), 2 * max(1.0, BubbleGeometry::WIDTH - $l['cx']));

            foreach ($l['lines'] as $i => $line) {
                $out[] = [
                    'left' => round(($l['cx'] - $width / 2) * $scale, 2),
                    'top' => round(($top + $i * $lineHeight) * $scale, 2),
                    'width' => round($width * $scale, 2),
                    'line' => round($lineHeight * $scale, 2),
                    'size' => round($l['font'] * $scale, 2),
                    'family' => $strong ? 'Montserrat XM' : 'Liberation Sans',
                    'bold' => $strong,
                    'italic' => $type === BubbleType::Thought || $type === BubbleType::DeviceVoice,
                    'html' => self::lineHtml($line, $strong),
                ];
            }
        }

        return $out;
    }

    /**
     * Ligne échappée ; mots en emphase en gras (le cri et le cartouche sont déjà gras).
     *
     * @param  list<array{text: string, bold: bool}>  $line
     */
    private static function lineHtml(array $line, bool $strong): string
    {
        return implode(' ', array_map(static fn (array $word): string => ! $strong && $word['bold'] ? '<b>'.e($word['text']).'</b>' : e($word['text']), $line));
    }

    /**
     * Illustration locale en JPEG (data URI) : public/assets/… ou disque public ; jamais une adresse distante.
     */
    private function imageData(?string $path): ?string
    {
        $path = ltrim(trim((string) $path), '/');

        if ($path === '' || preg_match('~^[a-z][a-z0-9+.\-]*://~i', $path) === 1) {
            return null;
        }

        $file = str_starts_with($path, 'assets/') ? public_path($path) : Storage::disk('public')->path($path);

        if (! is_file($file)) {
            return null;
        }

        $bytes = (string) file_get_contents($file);
        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            // Sans GD : JPEG et PNG passent tels quels, les autres formats donnent le repli.
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

            return in_array($mime, ['image/jpeg', 'image/png'], true) ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
        }

        $width = imagesx($image);

        if ($width > self::IMAGE_MAX_PX) {
            $scaled = imagescale($image, self::IMAGE_MAX_PX);
            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        ob_start();
        imagejpeg($image, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
