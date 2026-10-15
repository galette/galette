<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use Com\Tecnick\Pdf\Tcpdf;
use Galette\Core\I18n;
use Galette\Core\Preferences;
use Galette\Core\PrintLogo;
use Galette\Entity\PdfModel;

use function Safe\define;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mime_content_type;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\preg_split;
use function Safe\realpath;

if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', GALETTE_FONTS_PATH);
}

/**
 * PDF class for galette
 *
 * Positions are absolute, in millimeters from the top left corner of the page.
 * tc-lib-pdf has no current position; Galette keeps one, so documents can be
 * written sequentially.
 *
 * @author John Perr <johnperr@abul.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

abstract class Pdf extends Tcpdf
{
    public const string FONT = 'dejavusans';
    public const int FONT_SIZE = 10;
    public const string FORMAT = 'A4';

    protected I18n $i18n;
    private bool $paginated = false;
    protected string $filename;
    private bool $has_footer = true;
    protected float $footer_height = 0;
    private bool $finalized = false;
    //documents that add their pages by themselves must set it to false
    protected bool $add_first_page = true;

    protected string $orientation = 'P';
    /** @var array{left: float, top: float, right: float, bottom: float} */
    protected array $margins = ['left' => 10, 'top' => 10, 'right' => 10, 'bottom' => 20];
    protected bool $auto_page_break = true;

    protected float $x = 0;
    protected float $y = 0;
    protected string $font_style = '';
    protected float $font_size = self::FONT_SIZE;
    protected string $text_color = '#000000';
    protected string $draw_color = '#000000';
    protected float $line_height_ratio = 1.25;

    /**
     * Main constructor, set creator and author
     *
     * @param Preferences $preferences Preferences
     * @param ?PdfModel   $model       Related model
     */
    public function __construct(
        protected Preferences $preferences,
        private readonly ?PdfModel $model = null
    ) {
        global $i18n;

        $this->i18n = $i18n;
        parent::__construct(
            unit: 'mm',
            isunicode: true,
            subsetfont: true,
            compress: true,
            fileOptions: self::getFileOptions()
        );
        //tc-lib-pdf has no default horizontal cell padding, TCPDF had one
        $this->setDefaultCellPadding(top: 0, right: 1, bottom: 0, left: 1);
        $this->setCreator('Galette');
        $name = sprintf(
            _T("Association %s"),
            $this->preferences->pref_nom
        );
        $this->setAuthor(
            $name . ' (using Galette ' . GALETTE_VERSION . ')'
        );

        if ($this->supportsRTL() && $this->i18n->isRTL()) {
            $this->setRTL(enabled: true);
        }

        if ($model !== null) {
            $this->setTitle($this->model->htitle);
        }

        $this->init();
        if ($this->has_footer) {
            $this->calculateFooterHeight();
        }
        if ($this->add_first_page) {
            $this->newPage();
        }
    }

    /**
     * Files the library is allowed to read
     *
     * Files referenced from PDF models markup cannot be read from the disk:
     * logos are embedded by the replacements.
     *
     * @return array{allowedPaths: array<string>, markupAllowedPaths: array<string>}
     */
    public static function getFileOptions(): array
    {
        $paths = [
            GALETTE_FONTS_PATH,
            GALETTE_ROOT,
            GALETTE_DATA_PATH,
            GALETTE_PHOTOS_PATH,
            GALETTE_CACHE_DIR
        ];

        $allowed = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $allowed[] = realpath($path);
            }
        }

        return [
            'allowedPaths' => array_values(array_unique($allowed)),
            'markupAllowedPaths' => is_dir(GALETTE_FONTS_PATH) ? [realpath(GALETTE_FONTS_PATH)] : []
        ];
    }

    /**
     * Initialize PDF
     *
     * Called from the constructor, before the first page is added.
     * Set here document properties, margins and font.
     */
    public function init(): void
    {
        $this->setFont();
    }

    /**
     * Whether current document follows right-to-left languages
     */
    protected function supportsRTL(): bool
    {
        return true;
    }

    /**
     * No footer
     */
    protected function setNoFooter(): void
    {
        $this->has_footer = false;
    }

    /**
     * Set page margins
     *
     * @param float  $left   Left margin
     * @param float  $top    Top margin
     * @param ?float $right  Right margin, defaults to left one
     * @param ?float $bottom Bottom margin, keep current one if null
     */
    protected function setPageMargins(float $left, float $top, ?float $right = null, ?float $bottom = null): void
    {
        $this->margins['left'] = $left;
        $this->margins['top'] = $top;
        $this->margins['right'] = $right ?? $left;
        if ($bottom !== null) {
            $this->margins['bottom'] = $bottom;
        }
    }

    /**
     * Enable or disable automatic page breaks
     *
     * @param bool $auto Enable automatic page breaks
     */
    protected function setAutoPageBreak(bool $auto): void
    {
        $this->auto_page_break = $auto;
    }

    /**
     * Add a new page
     *
     * @param ?string $orientation Page orientation (P or L), keep current if null
     */
    public function newPage(?string $orientation = null): void
    {
        if ($orientation !== null) {
            $this->orientation = $orientation;
        }

        $bottom = $this->margins['bottom'];
        if ($this->has_footer) {
            $bottom = max($bottom, $this->footer_height + 15);
        }

        $this->addPage([
            'format' => self::FORMAT,
            'orientation' => $this->orientation,
            'margin' => [
                'PL' => $this->margins['left'],
                'PR' => $this->margins['right'],
                'PT' => $this->margins['top'],
                'PB' => $bottom,
                'HB' => 0,
                'FT' => 0
            ],
            'autobreak' => $this->auto_page_break
        ]);

        $this->x = $this->getLeft();
        $this->y = $this->margins['top'];
        $this->applyFont();
        $this->page->addContent($this->color->getPdfColor($this->text_color));
    }

    /**
     * Left position of the writable area
     */
    public function getLeft(): float
    {
        return $this->margins['left'];
    }

    /**
     * Width of the current page
     */
    public function getPageWidth(): float
    {
        return $this->page->getPage()['width'];
    }

    /**
     * Height of the current page
     */
    public function getPageHeight(): float
    {
        return $this->page->getPage()['height'];
    }

    /**
     * Number of pages
     */
    public function getNumPages(): int
    {
        return count($this->page->getPages());
    }

    /**
     * Width of the writable area
     */
    public function getContentWidth(): float
    {
        return $this->getPageWidth() - $this->margins['left'] - $this->margins['right'];
    }

    /**
     * Current horizontal position
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Current vertical position
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Set current position
     *
     * @param ?float $x Horizontal position, unchanged if null
     * @param ?float $y Vertical position, unchanged if null
     */
    public function setXY(?float $x = null, ?float $y = null): void
    {
        if ($x !== null) {
            $this->x = $x;
        }
        if ($y !== null) {
            $this->y = $y;
        }
    }

    /**
     * Add a new page if a content does not fit on the current one
     *
     * Text cells do not break pages by themselves.
     *
     * @param ?float $h Height of the content, current line height if null
     *
     * @return bool Whether a page has been added
     */
    public function checkPageBreak(?float $h = null): bool
    {
        if ($this->y + ($h ?? $this->getLineHeight()) > $this->page->getRegion()['RT']) {
            $this->newPage();
            return true;
        }
        return false;
    }

    /**
     * Line break
     *
     * @param ?float $h Height of the break, current line height if null
     */
    public function ln(?float $h = null): void
    {
        $this->x = $this->getLeft();
        $this->y += $h ?? $this->getLineHeight();
    }

    /**
     * Height of a text line with the current font
     */
    public function getLineHeight(): float
    {
        return $this->toUnit($this->font_size * $this->line_height_ratio);
    }

    /**
     * Set current font
     *
     * @param ?string $style Font style (B, I, BI, or empty string); keep current if null
     * @param ?float  $size  Font size in points; keep current if null
     */
    public function setFont(?string $style = null, ?float $size = null): void
    {
        if ($style !== null) {
            $this->font_style = $style;
        }
        if ($size !== null) {
            $this->font_size = $size;
        }
        $this->applyFont();
    }

    /**
     * Set current font size
     *
     * @param float $size Font size in points
     */
    public function setFontSize(float $size): void
    {
        $this->setFont(size: $size);
    }

    /**
     * Get current font size, in points
     */
    public function getFontSize(): float
    {
        return $this->font_size;
    }

    /**
     * Get current font style
     */
    public function getFontStyle(): string
    {
        return $this->font_style;
    }

    /**
     * Push current font to the font stack, and to the current page if any
     */
    private function applyFont(): void
    {
        if ($this->font->hasCurrentFont()) {
            //keep a single entry in the stack
            $this->font->popLastFont();
        }
        $font = $this->font->insert(
            objnum: $this->pon,
            font: self::FONT,
            style: $this->font_style,
            size: $this->font_size
        );
        if ($this->page->getPages() !== []) {
            $this->page->addContent($font['out']);
        }
    }

    /**
     * Set text color
     *
     * @param string $color Color, as #rrggbb
     */
    public function setTextColor(string $color): void
    {
        $this->text_color = $color;
        if ($this->page->getPages() !== []) {
            $this->page->addContent($this->color->getPdfColor($color));
        }
    }

    /**
     * Set lines color
     *
     * @param string $color Color, as #rrggbb
     */
    public function setDrawColor(string $color): void
    {
        $this->draw_color = $color;
    }

    /**
     * Get the width of a string
     *
     * @param string  $text  Text
     * @param ?string $style Font style, current one if null
     * @param ?float  $size  Font size, current one if null
     */
    public function stringWidth(string $text, ?string $style = null, ?float $size = null): float
    {
        $restore = ($style !== null && $style !== $this->font_style)
            || ($size !== null && $size !== $this->font_size);

        if ($restore) {
            $this->font->insert(
                objnum: $this->pon,
                font: self::FONT,
                style: $style ?? $this->font_style,
                size: $size ?? $this->font_size
            );
        }
        $width = $this->font->getOrdArrWidth($this->uniconv->strToOrdArr($text));
        if ($restore) {
            $this->font->popLastFont();
        }

        return $this->toUnit($width);
    }

    /**
     * Write a text cell
     *
     * The position moves to the next line, or to the right of the cell.
     *
     * @param string  $text   Text
     * @param float   $w      Cell width, up to the right margin if 0
     * @param float   $h      Cell minimal height, fits the text if 0
     * @param string  $align  Horizontal alignment (L, C, R, J), depends on text direction if empty
     * @param bool    $ln     Move to the next line after the cell
     * @param string  $valign Vertical alignment (T, C, B)
     * @param ?string $fill   Background color, as #rrggbb
     * @param string  $border Borders to draw: any of L, T, R, B, or 1 for all
     * @param ?float  $x      Horizontal position, current one if null
     * @param ?float  $y      Vertical position, current one if null
     * @param bool    $wrap   Wrap text on several lines; else it is compressed to fit the width
     *
     * @return float Height of the cell
     */
    public function text(
        string $text,
        float $w = 0,
        float $h = 0,
        string $align = '',
        bool $ln = true,
        string $valign = 'T',
        ?string $fill = null,
        string $border = '',
        ?float $x = null,
        ?float $y = null,
        bool $wrap = true
    ): float {
        $x ??= $this->x;
        $y ??= $this->y;
        if ($w <= 0) {
            $w = $this->getPageWidth() - $this->margins['right'] - $x;
        }
        if ($align === '') {
            $align = $this->rtl ? 'R' : 'L';
        }

        if ($border !== '' || $fill !== null) {
            $this->page->addContent(
                $this->getCellBox(
                    x: $x,
                    y: $y,
                    w: $w,
                    h: max($h, $this->getLineHeight()),
                    border: $border,
                    fill: $fill
                )
                //background has changed fill color, that is also the text one
                . $this->color->getPdfColor($this->text_color)
            );
        }

        $height = max($h, $this->getLineHeight());
        if ($text !== '') {
            $this->page->addContent(
                $this->getTextCell(
                    txt: $text,
                    posx: $x,
                    posy: $y,
                    width: $w,
                    //fitting text only applies to cells with a height
                    height: $wrap ? $h : $height,
                    valign: $valign,
                    halign: $align,
                    drawcell: false,
                    fit: $wrap ? '' : 'S'
                )
            );
            $height = max($height, $this->getLastCellBBox()['h']);
        }

        if ($ln) {
            $this->x = $this->getLeft();
            $this->y = $y + $height;
        } else {
            $this->x = $x + $w;
            $this->y = $y;
        }

        return $height;
    }

    /**
     * Draw a cell background and borders
     *
     * @param float   $x      Horizontal position
     * @param float   $y      Vertical position
     * @param float   $w      Width
     * @param float   $h      Height
     * @param string  $border Borders to draw: any of L, T, R, B, or 1 for all
     * @param ?string $fill   Background color, as #rrggbb
     */
    private function getCellBox(float $x, float $y, float $w, float $h, string $border, ?string $fill): string
    {
        $out = '';
        if ($fill !== null) {
            $out .= $this->graph->getRect(
                posx: $x,
                posy: $y,
                width: $w,
                height: $h,
                mode: 'F',
                styles: ['all' => ['fillColor' => $fill, 'lineWidth' => 0]]
            );
        }

        if ($border === '1') {
            $border = 'LTRB';
        }
        $style = ['lineWidth' => 0.2, 'lineColor' => $this->draw_color];
        $sides = [
            'L' => [$x, $y, $x, $y + $h],
            'T' => [$x, $y, $x + $w, $y],
            'R' => [$x + $w, $y, $x + $w, $y + $h],
            'B' => [$x, $y + $h, $x + $w, $y + $h]
        ];
        foreach ($sides as $side => $coords) {
            if (str_contains($border, $side)) {
                $out .= $this->graph->getLine(
                    posx1: $coords[0],
                    posy1: $coords[1],
                    posx2: $coords[2],
                    posy2: $coords[3],
                    style: $style
                );
            }
        }

        return $out;
    }

    /**
     * Draw a rectangle
     *
     * @param float   $x    Horizontal position
     * @param float   $y    Vertical position
     * @param float   $w    Width
     * @param float   $h    Height
     * @param ?string $fill Background color, as #rrggbb; no background if null
     * @param ?string $line Lines color, as #rrggbb; current draw color if null, no line if empty
     */
    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $line = null): void
    {
        $line ??= $this->draw_color;
        $style = [
            'lineWidth' => $line === '' ? 0 : 0.2,
            'lineColor' => $line === '' ? ($fill ?? 'white') : $line,
            'fillColor' => $fill ?? ''
        ];
        $mode = match (true) {
            $fill !== null && $line !== '' => 'DF',
            $fill !== null => 'F',
            default => 'S'
        };
        $this->page->addContent($this->graph->getRect(
            posx: $x,
            posy: $y,
            width: $w,
            height: $h,
            mode: $mode,
            styles: ['all' => $style]
        ));
    }

    /**
     * Draw a line
     *
     * @param float $x1    Start horizontal position
     * @param float $y1    Start vertical position
     * @param float $x2    End horizontal position
     * @param float $y2    End vertical position
     * @param float $width Line width
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.2): void
    {
        $this->page->addContent(
            $this->graph->getLine(
                posx1: $x1,
                posy1: $y1,
                posx2: $x2,
                posy2: $y2,
                style: ['lineWidth' => $width, 'lineColor' => $this->draw_color]
            )
        );
    }

    /**
     * Draw an image
     *
     * When both width and height are provided, the image is resized to fit
     * in the box, keeping its aspect ratio; it is placed at the top of the box,
     * and centered horizontally.
     *
     * @param string $file Image path
     * @param float  $x    Horizontal position
     * @param float  $y    Vertical position
     * @param float  $w    Width; computed from height if 0
     * @param float  $h    Height; computed from width if 0
     */
    public function image(string $file, float $x, float $y, float $w = 0, float $h = 0): void
    {
        $iid = $this->image->add($file);
        $dims = $this->image->getImageDimensionsByKey($this->image->getKey($file));
        $ratio = $dims['width'] / $dims['height'];

        if ($w <= 0 && $h <= 0) {
            //original size at 72 dpi
            $w = $this->toUnit($dims['width']);
            $h = $this->toUnit($dims['height']);
        } elseif ($h <= 0) {
            $h = $w / $ratio;
        } elseif ($w <= 0) {
            $w = $h * $ratio;
        } elseif ($w / $h > $ratio) {
            $new_w = $h * $ratio;
            $x += ($w - $new_w) / 2;
            $w = $new_w;
        } else {
            $h = $w / $ratio;
        }

        $this->page->addContent(
            $this->image->getSetImage(
                iid: $iid,
                xpos: $x,
                ypos: $y,
                width: $w,
                height: $h,
                pageheight: $this->getPageHeight()
            )
        );
    }

    /**
     * Calculate footer height
     *
     * Footer is rendered in a scratch document to measure it.
     */
    private function calculateFooterHeight(): void
    {
        $scratch = new Tcpdf(unit: 'mm', isunicode: true, subsetfont: true, fileOptions: self::getFileOptions());
        $scratch->font->insert(objnum: $scratch->pon, font: self::FONT, style: '', size: self::FONT_SIZE);
        $scratch->addPage(['format' => self::FORMAT, 'orientation' => $this->orientation]);
        $scratch->getHTMLCell(
            html: self::normalizeHtml($this->getFooterHtml()),
            posx: $this->margins['left'],
            posy: 0,
            width: $this->getFooterWidth($scratch->page->getPage()['width'])
        );
        $this->footer_height = $scratch->getLastHTMLCellEnd()['y'];
        if ($this->paginated) {
            $this->footer_height += 4;
        }
    }

    /**
     * Footer width
     *
     * @param float $page_width Page width
     */
    private function getFooterWidth(float $page_width): float
    {
        return $page_width - $this->margins['left'] - $this->margins['right'];
    }

    /**
     * Footer HTML contents
     */
    private function getFooterHtml(): string
    {
        if (isset($this->model)) {
            $hfooter = '';
            if (trim($this->model->hstyles) !== '') {
                $hfooter .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
            }
            return $hfooter . $this->model->hfooter;
        }

        $address = $this->preferences->getPostalAddress();
        $hfooter = '<style>div#pdf_footer {text-align: center;font-size: 0.7em;}</style>';
        $hfooter .= '<div id="pdf_footer">' . nl2br($address) . '</div>';
        return $hfooter;
    }

    /**
     * Set show pagination
     */
    public function showPagination(): void
    {
        $this->paginated = true;
    }

    /**
     * Draws page header, repeated on each page
     *
     * @param int $page  Page number, starting at 1
     * @param int $total Total pages
     */
    protected function drawPageHeader(int $page, int $total): void
    {
        //no header by default
    }

    /**
     * Draws page footer
     *
     * @param int $page  Page number, starting at 1
     * @param int $total Total pages
     */
    protected function drawPageFooter(int $page, int $total): void
    {
        $y = $this->getPageHeight() - $this->footer_height - 10;
        //footer is outside the writable area, add it without page break handling
        $this->page->addContent(
            $this->getHTMLCell(
                html: self::normalizeHtml($this->getFooterHtml()),
                posx: $this->margins['left'],
                posy: $y,
                width: $this->getFooterWidth($this->getPageWidth())
            )
        );

        if ($this->paginated) {
            $this->setFont('', self::FONT_SIZE - 3);
            $this->text(
                text: $page . '/' . $total,
                h: 4,
                align: $this->rtl ? 'L' : 'R',
                x: $this->margins['left'],
                y: $this->getLastHTMLCellEnd()['y']
            );
        }
    }

    /**
     * Draw headers and footers on all pages
     *
     * Done at the end, when the number of pages is known.
     */
    private function finalize(): void
    {
        if ($this->finalized) {
            return;
        }
        $this->finalized = true;

        $pages = array_keys($this->page->getPages());
        $total = count($pages);
        foreach ($pages as $num => $pid) {
            $this->setCurrentPage($pid);
            $this->setFont('', self::FONT_SIZE);
            $this->setTextColor('#000000');
            $this->drawPageHeader($num + 1, $total);
            if ($this->has_footer) {
                $this->drawPageFooter($num + 1, $total);
            }
        }
    }

    /**
     * Draws PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     */
    public function PageHeader(?string $title = null): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        if (isset($this->model)) {
            $this->modelPageHeader($title);
        } else {
            $this->standardPageHeader($title);
        }
    }

    /**
     * Draws models PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     */
    protected function modelPageHeader(?string $title = null): void
    {
        $html = '';
        if (trim($this->model->hstyles) !== '') {
            $html .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
        }
        $html .= "<div dir=\"" . ($this->i18n->isRTL() ? 'rtl' : 'ltr') . "\">" . $this->model->hheader . "</div>";
        $this->writeHTML($html);

        if ($title !== null) {
            $this->writeHTML('<h2 style="text-align:center;">' . $title . '</h2>');
        }

        if (trim($this->model->title) !== '') {
            $htitle = '';
            if (trim($this->model->hstyles) !== '') {
                $htitle .= "<style>\n" . $this->model->hstyles
                    . "\n</style>\n\n";
            }
            $htitle .= '<div id="pdf_title">' . $this->model->htitle . '</div>';
            $this->writeHTML($htitle);
        }
        if (trim($this->model->subtitle) !== '') {
            $hsubtitle = '';
            if (trim($this->model->hstyles) !== '') {
                $hsubtitle .= "<style>\n" . $this->model->hstyles
                    . "\n</style>\n\n";
            }
            $hsubtitle .= '<div id="pdf_subtitle">' . $this->model->hsubtitle
                . '</div>';
            $this->writeHTML($hsubtitle);
        }
        if (
            trim($this->model->title) !== ''
            || trim($this->model->subtitle) !== ''
        ) {
            $this->ln(5);
        }
    }

    /**
     * Draws standard PDF page header
     *
     * @param ?string $title Additional title to display just after logo
     */
    protected function standardPageHeader(?string $title = null): void
    {
        //default header
        $print_logo = new PrintLogo();
        $logofile = $print_logo->getPath();

        // Set logo size to max width 30 mm or max height 25 mm
        $ratio = $print_logo->getWidth() / $print_logo->getHeight();
        if ($ratio < 1) {
            $hlogo = $print_logo->getHeight() > 16 ? 25 : $print_logo->getHeight();
            $wlogo = round($hlogo * $ratio);
        } else {
            $wlogo = $print_logo->getWidth() > 16 ? 30 : $print_logo->getWidth();
            $hlogo = round($wlogo / $ratio);
        }

        $font_style = $this->font_style;
        $font_size = $this->font_size;
        $this->setFont('B', self::FONT_SIZE + 4);
        $this->setTextColor('#000000');

        $this->ln(2);
        $ystart = $this->getY();

        $this->text(
            text: $this->preferences->pref_nom,
            w: $this->getContentWidth() - $wlogo,
            h: 6
        );
        $this->setFont('B', self::FONT_SIZE + 2);

        if ($title !== null) {
            $this->text(
                text: $title,
                w: $this->getContentWidth() - $wlogo,
                h: 6
            );
        }
        $yend = $this->getY(); //store position at the end of the text

        if ($this->rtl) {
            $x = $this->getLeft();
        } else {
            $x = $this->getPageWidth() - $this->margins['right'] - $wlogo; //right align
        }
        $this->image(file: $logofile, x: $x, y: $ystart, w: $wlogo, h: $hlogo);
        //if position after logo is < than position after text,
        //we have to change y
        $this->setXY($this->getLeft(), max($ystart + $hlogo + 3, $yend));
        $this->setFont($font_style, $font_size);
    }

    /**
     * Draws body from model
     */
    public function PageBody(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $hbody = '';
        if (trim($this->model->hstyles) !== '') {
            $hbody .= "<style>\n" . $this->model->hstyles . "\n</style>\n\n";
        }
        $hbody .= $this->model->hbody;
        $this->writeHTML($hbody);
    }

    /**
     * Output HTML content at current position, then move after it
     *
     * Content goes on following pages if needed.
     *
     * @param string $html HTML content
     * @param ?float $x    Horizontal position, left margin if null
     * @param ?float $y    Vertical position, current one if null
     * @param float  $w    Width, up to the right margin if 0
     * @param float  $h    Height, fits the content if 0
     */
    public function writeHTML(string $html, ?float $x = null, ?float $y = null, float $w = 0, float $h = 0): void
    {
        $x ??= $this->getLeft();
        $y ??= $this->y;
        if ($w <= 0) {
            $w = $this->getPageWidth() - $this->margins['right'] - $x;
        }

        $this->addHTMLCell(
            html: self::normalizeHtml($html),
            posx: $x,
            posy: $y,
            width: $w,
            height: $h
        );

        $end = $this->getLastHTMLCellEnd();
        $this->x = $this->getLeft();
        $this->y = $h > 0 ? max($y + $h, $end['y']) : $end['y'];
        //restore text color, that HTML may have changed
        $this->page->addContent($this->color->getPdfColor($this->text_color));
    }

    /**
     * Build an HTML table, with a header repeated on each page
     *
     * Columns order is reversed for right-to-left languages; the PDF engine does not mirror tables.
     *
     * @param array<string>        $widths       Columns widths
     * @param array<string>        $headers      Header cells, as th elements
     * @param array<array<string>> $rows         Rows cells, as td elements
     * @param string               $border_color Cells border color, as #rrggbb
     * @param string               $row_attrs    Additional attributes for body rows
     */
    protected function getHTMLTable(
        array $widths,
        array $headers,
        array $rows,
        string $border_color = '#000000',
        string $row_attrs = ''
    ): string {
        $order = fn(array $cells): array => $this->rtl ? array_reverse($cells) : $cells;

        $html = '<style>th, td {border: 0.2mm solid ' . $border_color . '; vertical-align: middle;}</style>'
            . '<table cellpadding="4" style="font-size:' . $this->font_size . 'pt;"><colgroup>';
        foreach ($order($widths) as $width) {
            $html .= '<col width="' . $width . '"/>';
        }
        $html .= '</colgroup><thead><tr style="font-weight:bold;text-align:center;">'
            . implode('', $order($headers))
            . '</tr></thead><tbody>';
        foreach ($rows as $cells) {
            $html .= '<tr' . ($row_attrs !== '' ? ' ' . $row_attrs : '') . '>' . implode('', $order($cells)) . '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Get an image as a data URI, to embed it in HTML content
     *
     * @param string $path Image path
     */
    public static function getDataUri(string $path): string
    {
        return 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Escape text to be used in HTML content
     *
     * @param ?string $text Text
     */
    public static function escape(?string $text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Drop HTML insignificant whitespace
     *
     * Web browsers ignore the whitespace that follows a line break or a block opening
     * tag; the PDF engine may render it as a leading space instead. As PDF models are
     * stored indented, each indented line would then be shifted from the others.
     *
     * @param string $html HTML content
     */
    public static function normalizeHtml(string $html): string
    {
        //do not alter <pre> contents, whitespace is significant there
        $parts = preg_split(
            pattern: '#(<pre\b.*?</pre>)#si',
            subject: $html,
            limit: -1,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $blocks = 'br|div|p|table|thead|tbody|tr|td|th|ul|ol|li|dl|dt|dd|blockquote|h[1-6]';
        foreach ($parts as &$part) {
            if (preg_match('#^<pre\b#i', $part)) {
                continue;
            }
            $part = preg_replace('#(<(?:' . $blocks . ')\b[^>]*>)\s+#i', '$1', $part);
        }

        return implode('', $parts);
    }

    /**
     * Fix text size
     *
     * Decrease font size until the text fits, then use it.
     *
     * @param string $text      Text content
     * @param float  $maxsize   Maximal size
     * @param float  $fontsize  Font size
     * @param string $fontstyle Font style (defaults to '')
     */
    protected function fixSize(
        string $text,
        float $maxsize,
        float $fontsize,
        string $fontstyle = ''
    ): void {
        while ($fontsize > 1 && (int)$this->stringWidth($text, $fontstyle, $fontsize) > $maxsize) {
            $fontsize--;
        }
        $this->setFont($fontstyle, $fontsize);
    }

    /**
     * Cut a string
     *
     * @param string $str    Original string
     * @param int    $length Max length
     */
    protected function cut(string $str, int $length): string
    {
        $length -= 2; //keep a margin
        if ((int)$this->stringWidth($str) > $length) {
            while ((int)$this->stringWidth($str . '...') > $length) {
                $str = mb_substr(string: $str, start: 0, length: -1, encoding: 'UTF-8');
            }
            $str .= '...';
        }
        return $str;
    }

    /**
     * Prepare a header string
     *
     * Header font is set; write the string with a text cell that does not wrap,
     * it will be horizontally compressed if it does not fit.
     *
     * @param string $str    Original string
     * @param int    $length Max length
     */
    protected function stretchHead(string $str, int $length): string
    {
        $this->setFont('B', self::FONT_SIZE);
        return $str;
    }

    /**
     * Get filename
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get PDF contents
     */
    public function getContents(): string
    {
        $this->finalize();
        return $this->getOutPDFString();
    }

    /**
     * Store PDF in a file
     *
     * @param string $path File path
     */
    protected function storeAs(string $path): void
    {
        file_put_contents($path, $this->getContents());
    }
}
