<?php
/**
 * @author Spaceboy
 */
namespace Spaceboy\Epub;


use Spaceboy\Epub\Exception;
use Spaceboy\Epub\Creator;
use Spaceboy\Epub\Book;
use Spaceboy\Epub\Chapter;
use Spaceboy\SpaceTools\SpaceTools;


class Epub
{
    private string $tmpDir;

    /** @var Books[] */
    private array $books = [];

    /** @var string UUID */
    private string $uuid;

    /** @var string[] list of fonts */
    private array $fonts = [];

    /** @var string[] list of images */
    private array $images = [];

    /** @var string[] list of styles */
    private array $styles = [];

    /** @var string cover image */
    private ?string $cover = null;

    /** @var string cover title */
    private string $coverTitle = 'Cover';

    /** @var string publisher's name */
    private ?string $publisher = null;

    /** @var private publication description */
    private ?string $description = null;

    /** @var Creator[] */
    private array $creators = [];

    /** @var string publication title */
    private ?string $title = null;

    /** @var string[] array of subjects */
    private array $subjects = [];

    /** @var string language */
    private ?string $language = null;

    /** @var string $chapterHeader */
    private string $chapterHeader = <<<HEADER
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en">
    <head>
HEADER;

    /** @var string $chapterFooter */
    private string $chapterFooter = <<<FOOTER
    </body>
</html>
FOOTER;

    /** @var boolean convert HTML entities to chars? */
    private $decodeEntities = true;

    /** @var integer flags for converting HTML entities */
    private $decodeEntFlags = ENT_QUOTES | ENT_HTML401;

    /** @var string|null content title */
    private ?string $contentTitle = null;

    /** @var bool content position (true = at beginning; false = at the end of publication) */
    private bool $contentAtBegin;

    /** @var string PHTML template file full path and name */
    private string $templateCover = __DIR__ . '/templates/cover.phtml';

    /** @var \Closure|null method called before publacition is build (called with @param Epub &$epub) */
    private ?\Closure $beforeBuild = null;


    const   META_INF    = 'META-INF';
    const   OEBPS       = 'OEBPS';
    const   FONTS       = 'Fonts';
    const   IMAGES      = 'Images';
    const   STYLES      = 'Styles';
    const   TEXTS       = 'Text';
    const   COVER       = 'cover.xhtml';


    /**
     * Class constructor.
     * @param string $tmpDir cesta k pracovnímu adresáři
     */
    public function __construct(string $tmpDir)
    {
        $this->uuid = $this->getUUID();
        $this->tmpDir = $this->createWorkspace($tmpDir);
    }

    /**
     * Class destructor.
     */
    public function __destruct()
    {
        $this->clearTemp();
    }

    /**
     * Set publication description.
     * @param string $title
     * @return Epub
     */
    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set publisher.
     * @param string $publisher
     * @return Epub
     */
    public function setPublisher($publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }

    /**
     * Set publication title.
     * @param string $title
     * @return Epub
     */
    public function setTitle($title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Add publication creator.
     * @param string $title
     * @return Epub
     */
    public function addCreator($role, $name, $nameFileAs = null): self
    {
        $this->creators[] = new Creator($role, $name, $nameFileAs);
        return $this;
    }

    /**
     * Set publication language.
     * @param string $language
     * @return Epub
     */
    public function setLanguage($language): self
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Add book into publication.
     * @param string $bookTitle
     * @return Book
     */
    public function addBook($bookTitle): Book
    {
        $book = new Book($this, sizeof($this->books) + 1, $bookTitle);
        $this->books[] = $book;
        return $book;
    }

    /**
     * Get book list.
     * @return array
     */
    public function getBooks(): array
    {
        return $this->books;
    }

    /**
     * Add image (from file).
     * @param string filename
     * @return Epub
     * @throws EpubException
     */
    public function addImage(string $fileName): self
    {
        $this->images[] = $this->addFile($fileName, static::IMAGES);
        return $this;
    }

    /**
     * Add cover image (from file).
     * @param string filename
     * @param string cover title
     * @return Epub
     * @throws EpubException
     */
    public function addCover(string $fileName, string $coverTitle = null): self
    {
        $this->cover    = $this->addFile($fileName, static::IMAGES);
        if ($coverTitle) {
            $this->coverTitle   = $coverTitle;
        }
        return $this;
    }

    /**
     * Add CSS file.
     * @param string filename
     * @return Epub
     * @throws EpubException
     */
    public function addStyle(string $fileName): self
    {
        $this->styles[] = $this->addFile($fileName, static::STYLES);
        return $this;
    }

    /**
     * Add font file.
     * @param string filename
     * @return Epub
     * @throws EpubException
     */
    public function addFont(string $fileName): self
    {
        $this->fonts[]  = $this->addFile($fileName, static::FONTS);
        return $this;
    }

    /**
     * Add subject (keyword).
     * @param string $subject
     * @return Epub
     */
    public function addSubject(string $subject): self
    {
        $this->subjects[] = $subject;
        return $this;
    }

    /**
     * Set chapter wrapper header from HTML.
     * @param string $html HTML text hlavičky
     * @return Epub
     */
    public function setChapterHeader(string $html): self
    {
        $this->chapterHeader    = $html;
        return $this;
    }

    /**
     * Set chapter wrapper header from file.
     * @param string $fileName
     * @return Epub
     * @throws EpubException
     */
    public function setChapterHeaderFile(string $fileName): self
    {
        if (!is_file($fileName) || (!is_readable($fileName))) {
            throw new EpubException("Header is not file or is not readable ({$fileName}).");
        }
        return $this->setChapterHeader(file_get_contents($fileName));
    }

    /**
     * Set chapter footer from HTML.
     * @param string $html
     * @return Epub
     */
    public function setChapterFooter(string $html): self
    {
        $this->chapterFooter = $html;
        return $this;
    }

    /**
     * Set chapter wrapper footer from file.
     * @param string $fileName
     * @return Epub
     * @throws EpubException
     */
    public function setChapterFooterFile($fileName): self
    {
        if (!is_file($fileName) || (!is_readable($fileName))) {
            throw new EpubException("Footer is not file or is not readable ({$fileName}).");
        }
        return $this->setChapterFooter(file_get_contents($fileName));
    }

    /**
     * Get working directory path.
     * @return string
     */
    public function getTempDir(): string
    {
        return $this->tmpDir;
    }

    /**
     * Clear working directory.
     */
    public function clearTemp(): void
    {
        SpaceTools::purgeDir($this->getTempDir());
    }

    /**
     * Set title and position of publication content.
     * @param string $title
     * @param bool $atBegin
     * @return Epub
     */
    public function placeContent(string $title, bool $atBegin = false): self
    {
        $this->contentTitle = $title;
        $this->contentAtBegin = $atBegin;
        return $this;
    }

    /**
     * Set method runned before epub build.
     * @param callable $function
     * @return Epub
     */
    public function runBeforeBuild(callable $function): self
    {
        $this->beforeBuild = \Closure::fromCallable($function);
        return $this;
    }

    /**
     * Create epub.
     * @param string $outputFile
     */
    public function save(string $outputFile): void
    {
        // Convert HTML entities:
        if ($this->decodeEntities) {
            $this->entitiesDecode();
        }

        // Wrap chapter files:
        if ($this->chapterHeader || $this->chapterFooter) {
            $this->wrapChapter();
        }

        // Run "beforeBuild" functions:
        $this->manageBeforeFunctions();

        // Create HTML content.
        if ($this->contentTitle !== null) {
            $this->createHtmlContent();
        }

        // Create cover, TOC, content:
        $this->createCoverFile();
        $this->createTocFile();
        $this->createContentFile();

        // Create epub:
        $this->createArchive($outputFile);

        // ebook-convert source.epub .mobi --share-not-sync
    }

    /**
     * Add file (general).
     * @param string filename
     * @param string dir
     * @return string
     * @throws EpubException
     */
    private function addFile(string $fileName, $dir): string
    {
        if (!is_file($fileName) || (!is_readable($fileName))) {
            throw new EpubException("Not file or not readable file ({$fileName}).");
        }
        $name   = $dir . DIRECTORY_SEPARATOR . basename($fileName);
        if (!copy($fileName, $this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . $name)) {
            throw new EpubException("Can not copy file to temporary directory ({$fileName}).");
        }
        return $name;
    }

    /**
     * Decode HTML entities in chapter file(s).
     */
    private function entitiesDecode(): void
    {
        foreach ($this->books AS $book) {
            foreach ($book->getChapters() AS $chapter) {
                $fileName   = $this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . static::TEXTS . DIRECTORY_SEPARATOR . $chapter->getFileName();
                $fileCont   = file_get_contents($fileName);
                file_put_contents(
                    $fileName,
                    html_entity_decode($fileCont)
                );
            }
        }
    }

    /**
     * Wrap chapter file(s) with header and/or footer.
     */
    private function wrapChapter(): void
    {
        foreach ($this->books AS $book) {
            foreach ($book->getChapters() AS $chapter) {
                if (!$chapter->getWrap()) {
                    continue;
                }
                $fileName   = $this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . static::TEXTS . DIRECTORY_SEPARATOR . $chapter->getFileName();
                $fileCont   = file_get_contents($fileName);
                file_put_contents(
                    $fileName,
                    ($this->chapterHeader ?: '') . $fileCont . ($this->chapterFooter ?: '')
                );
            }
        }
    }

    /**
     * Add whole directory to ZIP archive.
     * @param \ZipArchive &$$archive
     * @param string $dirName
     * @param ?string $root
     */
    private function includeDir(\ZipArchive &$archive, string $dirName, string $root = ''): void
    {
        $dir = opendir($dirName);
        while ($fileName = readdir($dir)) {
            if (in_array($fileName, ['.', '..'])) {
                continue;
            }
            $fullName = $dirName . DIRECTORY_SEPARATOR . $fileName;
            if (is_dir($fullName)) {
                $this->includeDir($archive, $fullName, $root . DIRECTORY_SEPARATOR . $fileName);
            } else {
                $archive->addFile($fullName, substr($root . DIRECTORY_SEPARATOR . $fileName, 1));
            }
        }
    }

    /**
     * Create ZIP archive containing work dir.
     * @param string $fileName
     */
    private function createArchive(string $fileName): void
    {
        $zipArchive = new \ZipArchive();
        if (true !== $zipArchive->open($fileName, (\ZipArchive::CREATE | \ZipArchive::OVERWRITE))) {
            throw new EpubException("Failed to create publication ({$fileName}).");
        }
        $this->includeDir($zipArchive, $this->tmpDir);
        if ($zipArchive->status != \ZIPARCHIVE::ER_OK) {
            throw new EpubException("Can not build publication ({$fileName}).");
        }
        $zipArchive->close();
    }

    private function explodeString($str, $parts)
    {
        $start = 0;
        $out = [];
        foreach ($parts AS $part) {
            $out[] = substr($str, $start, $part);
            $start += $part;
        }
        return $out;
    }

    /**
     * Create random UUID by RFC 4122 rules
     * (see https://www.cryptosys.net/pki/uuid-rfc4122.html).
     * @return string
     */
    private function getUUID(): string
    {
        $str = $this->explodeString(strtoupper(bin2hex(random_bytes(16))), [8, 4, 3, 3, 12]);
        return $str[0]
            . '-' . $str[1]
            . '-4' . $str[2]
            . '-' . substr('89AB', rand(0, 3), 1) . $str[3]
            . '-' . $str[4];
    }

    /**
     * Create directory structure by description:
     * [
     *    dirname => subdirectories[]
     * ].
     * @param string $root root dir
     * @param array $str structure
     * @throws EpubException
     */
    private function createDirStr(string $root, array $structure): void
    {
        foreach ($structure AS $key => $val) {
            $dir    = $root . DIRECTORY_SEPARATOR . $key;
            if (!mkdir($dir)) {
                throw new EpubException("Can not create dir ({$dir}).");
            }
            if (is_array($val)) {
                $this->createDirStr($dir, $val);
            }
        }
    }

    /**
     * Create temp dir, working dir, directory structure & base files.
     * @param string $tmpDir
     * @return string working directory path
     * @throws EpubException
     */
    private function createWorkspace(string $tmpDir): string
    {
        if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
            throw new EpubException("Temp dir is not dir or is not writable ({$tmpDir}).");
        }
        $dir    = $tmpDir . DIRECTORY_SEPARATOR . uniqid();
        if (!mkdir($dir)) {
            throw new EpubException("Can not create temporary dir ({$dir}).");
        }
        // Create directory structure:
        $this->createDirStr($dir, [
            static::META_INF    => NULL,
            static::OEBPS       => [
                static::FONTS       => NULL,
                static::IMAGES      => NULL,
                static::STYLES      => NULL,
                static::TEXTS       => NULL,
            ],
        ]);
        // Vlož povinný soubor 'mimetype':
        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'mimetype',
            'application/epub+zip'
        );
        // Vlož povinný soubor 'container':
        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . static::META_INF . DIRECTORY_SEPARATOR . 'container.xml',
            (new \XmlEasyWriter('1.0', 'UTF-8'))
                ->startElement('container', [
                    'version'       => '1.0',
                    'xmlns'         => 'urn:oasis:names:tc:opendocument:xmlns:container',
                ])
                ->startElement('rootfiles')
                ->insertElement('rootfile', [
                    'full-path'     => static::OEBPS . DIRECTORY_SEPARATOR . 'content.opf',
                    'media-type'    => 'application/oebps-package+xml',
                ])
                ->endElement() // /rootfiles
                ->endElement() // /container
                ->endDocument()
                ->outputMemory()
        );
        return $dir;
    }

    /**
     * Create cover file.
     */
    private function createCoverFile(): void
    {
        if (!$this->cover) {
            return;
        }
        $fileName   = $this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . static::TEXTS . DIRECTORY_SEPARATOR . static::COVER;
        if (file_exists($fileName)) {
            return;
        }
        // Relative position from epub root:
        $cover = '../' . $this->cover;
        // Create content from PHTML:
        ob_start();
        include($this->templateCover);
        file_put_contents($fileName, ob_get_clean());
    }

    /**
     * Create file "content.opf".
     */
    private function createContentFile(): void
    {
        $xw = new \XmlEasyWriter('1.0', 'UTF-8', 'yes');

        $xw->startElement('package', [
            'xmlns'     => 'http://www.idpf.org/2007/opf',
            'unique-identifier' => 'uuid_id',
            'version'           => '2.0',
        ]);

        // Metadata:
        $xw->startElement('metadata', [
            'xmlns:calibre' => 'http://calibre.kovidgoyal.net/2009/metadata',
            'xmlns:dc'      => 'http://purl.org/dc/elements/1.1/',
            'xmlns:dcterms' => 'http://purl.org/dc/terms/',
            'xmlns:opf'     => 'http://www.idpf.org/2007/opf',
            'xmlns:xsi'     => 'http://www.w3.org/2001/XMLSchema-instance',
        ]);

        foreach ($this->creators AS $creator) {
            $xw->insertElement(
                'dc:creator',
                [
                    'opf:file-as'   => $creator->getNameFileAs(),
                    'opf:role'      => $creator->getRole(),
                ],
                $creator->getName()
            );
        }
        if ($this->title) {
            $xw->insertElement('dc:title', NULL, $this->title);
        }
        if ($this->description) {
            $xw->insertElement('dc:description', NULL, $this->title);
        }
        if ($this->language) {
            $xw->insertElement('dc:language', NULL, $this->language);
        }
        if ($this->publisher) {
            $xw->insertElement('dc:publisher', NULL, $this->publisher);
        }
        if ($this->cover) {
            $xw->insertElement('meta', [
                'name'      => 'cover',
                'content'   => basename($this->cover),
            ]);
        }
        $xw->insertElement(
            'dc:identifier', [
                'id'            => 'uuid_id',
                'opf:scheme'    => 'uuid',
            ],
            $this->uuid
        );

        foreach ($this->subjects AS $subject) {
            $xw->insertElement('dc:subject', NULL, $subject);
        }

        $xw->endElement(); // /metadata

        // Manifest:
        $xw->startElement('manifest');
            // Vložíme obal:
            if ($this->cover) {
                $xw->insertElement('item', [
                    'href'          => $this->cover,
                    'id'            => basename($this->cover),
                    'media-type'    => mime_content_type($this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . $this->cover),
                ]);
                $xw->insertElement('item', [
                    'href'          => static::TEXTS . DIRECTORY_SEPARATOR . static::COVER,
                    'id'            => static::COVER,
                    'media-type'    => 'application/xhtml+xml',
                ]);
            }
            // Zařadíme texty:
            foreach ($this->books AS $book) {
                foreach ($book->getChapters() AS $chapter) {
                    $fileName   = $chapter->getFileName();
                    $xw->insertElement('item', [
                        'href'          => static::TEXTS . DIRECTORY_SEPARATOR . $fileName,
                        'id'            => 'txt' . $fileName,
                        'media-type'    => 'application/xhtml+xml',
                    ]);
                }
            }
            // Najdeme styly:
            foreach ($this->styles AS $fileName) {
                $xw->insertElement('item', [
                    'href'          => $fileName,
                    'id'            => 'css' . basename($fileName),
                    'media-type'    => 'text/css',
                ]);
            }
            // Zařadíme fonty:
            foreach ($this->fonts AS $fileName) {
                $xw->insertElement('item', [
                    'href'          => $fileName,
                    'id'            => 'fnt' . basename($fileName),
                    'media-type'    => mime_content_type($this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . $fileName),
                ]);
            }
            // Zařadíme obrázky:
            foreach ($this->images AS $fileName) {
                $xw->insertElement('item', [
                    'href'          => $fileName,
                    'id'            => 'img' . basename($fileName),
                    'media-type'    => mime_content_type($this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . $fileName),
                ]);
            }
            // Obsah (toc.ncx):
            $xw->insertElement('item', [
                'href'          => 'toc.ncx',
                'id'            => 'ncx',
                'media-type'    => 'application/x-dtbncx+xml',
            ]);
        $xw->endElement(); // /manifest

        // Spine
        $xw->startElement('spine', [
            'toc'           => 'ncx',
        ]);
        foreach ($this->books AS $book) {
            if ($this->cover) {
                $xw->insertElement('itemref', [
                    'idref'         => static::COVER,
                ]);
            }
            foreach ($book->getChapters() AS $chapter) {
                $xw->insertElement('itemref', [
                    'idref'         => 'txt' . $chapter->getFileName(),
                ]);
            }
        }
        $xw->endElement(); // /spine

        // Guide:
        if ($this->cover) {
            $xw
                ->startElement('guide')
                ->insertElement('reference', [
                    'type'  => 'cover',
                    'title' => $this->coverTitle,
                    'href'  => static::TEXTS . DIRECTORY_SEPARATOR . static::COVER,
                ])
                ->endElement(); // /guide
        }

        $xw->endElement(); // /package

        // Uložíme soubor:
        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . 'content.opf', $xw->outputMemory());
    }

    /**
     * Vytvoř soubor toc.ncx.
     */
    private function createTocFile(): void
    {
        $playOrder  = 1;
        $xw = new \XmlEasyWriter('1.0', 'UTF-8', 'no');
        $xw
            // Root element:
            ->startElement('ncx', [
                'xmlns'     => 'http://www.daisy.org/z3986/2005/ncx/',
                'version'   => '2005-1',
                'xml:lang'  => 'ces',
            ])

            // Hlavička:
            ->startElement('head')
            ->startElement('meta', [
                'content'   => $this->uuid,
                'name'      => 'dtb:uid',
            ])->endElement()
            ->startElement('meta', [
                'content'   => 'Spaceboy/Epub PHP library',
                'name'      => 'dtb:generator',
            ])->endElement()
            ->endElement() // /head

            // Titul:
            ->startElement('docTitle')
            ->insertElement('text', NULL, 'Ilegální pradědeček')
            ->endElement() // /docTitle

            ->startElement('navMap')
        ;

        // Vložíme knihy:
        if (1 === sizeof($this->books)) {
            // Pokud máme jen jednu knihu, vložíme kapitoly jednoduše za sebou:
            foreach ($this->books[0]->getChapters() AS $chapter) {
                $fileName   = $chapter->getFileName();
                $xw
                    ->startElement('navPoint', [
                        'class'     => 'chapter',
                        'id'        => $fileName,
                        'playOrder' => $playOrder++,
                    ])
                    ->startElement('navLabel')
                    ->insertElement('text', NULL, $chapter->getTitle())
                    ->endElement() // /navLabel
                    ->insertElement('content', [
                        'src'   => static::TEXTS . DIRECTORY_SEPARATOR . $fileName,
                    ])
                    ->endElement() // /navPoint
                ;
            }
            $xw->endElement() // /navPoint
            ;
        } else {
            // Pokud máme více knih, vkládáme kapitoly pod knihy:
            foreach ($this->books AS $book) {
                $xw
                    ->startElement('navPoint', [
                        'class'     => 'book',
                        'id'        => "book{$book->getNumber()}",
                        'playOrder' => $playOrder++,
                    ])
                    ->startElement('navLabel')
                    ->insertElement('text', NULL, $book->getTitle())
                    ->endElement() // /navLabel
                    ->insertElement('content', [
                        'src'   => '',
                    ])
                ;
                // Vložíme kapitoly:
                foreach ($book->getChapters() AS $chapter) {
                    $fileName   = $chapter->getFileName();
                    $xw
                        ->startElement('navPoint', [
                            'class'     => 'chapter',
                            'id'        => $fileName,
                            'playOrder' => $playOrder++,
                        ])
                        ->startElement('navLabel')
                        ->insertElement('text', NULL, $chapter->getTitle())
                        ->endElement() // /navLabel
                        ->insertElement('content', [
                            'src'   => static::TEXTS . DIRECTORY_SEPARATOR . $fileName,
                        ])
                        ->endElement() // /navPoint
                    ;
                }
                $xw->endElement() // /navPoint
                ;
            }
        }

        // Ukončíme XML:
        $xw
            ->endElement() // /navMap
            ->endElement() // /ncx
        ;

        file_put_contents($this->tmpDir . DIRECTORY_SEPARATOR . static::OEBPS . DIRECTORY_SEPARATOR . 'toc.ncx', $xw->outputMemory());
    }

    private function createHtmlContent()
    {
        if (count($this->books) === 1) {
            $html =
                "<h3>{$this->contentTitle}</h3>"
                . PHP_EOL
                . "<ul>"
                . PHP_EOL
                ;
            foreach ($this->books[0]->getChapters() as $chapter) {
                $html .=
                    '<li>'
                    . "<a href=\"{$chapter->getFilename()}\">{$chapter->getTitle()}</a>"
                    . '</li>'
                    . PHP_EOL
                    ;
            }
            $html .=
                "<li><a href=\"content.html\">{$this->contentTitle}</a></li>"
                . PHP_EOL
                . '</ul>'
                . PHP_EOL
                ;
            $this->books[0]->addChapterHtml($html, 'content.html')->setTitle($this->contentTitle);
        } else {
            // Not implemented for multiple books publications
            // foreach ($this->books as $book) {
            // }
            // @@todo: implement
        }
    }

    private function manageBeforeFunctions(): void
    {
        if (is_callable($this->beforeBuild)) {
            call_user_func($this->beforeBuild, $this);
        }
        foreach ($this->books as $book) {
            $book->manageBeforeFunctions($this);
        }
    }
}
