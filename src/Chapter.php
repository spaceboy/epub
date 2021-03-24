<?php
namespace Spaceboy\Epub;

use Spaceboy\Epub\Exception;
use Spaceboy\Epub\Epub;
use Spaceboy\Epub\Book;

class Chapter {

    /** @var Book $book */
    private Book $book;

    /** @var string chapter filename */
    private $fileName;

    /** @var string */
    private $title = 'Chapter';

    /** @var bool */
    private bool $wrap = true;

    /** @var string[] list of styles */
    private array $styles = [];

    /** @var string[] list of images */
    private array $images = [];

    /** @var \Closure|null method called before publacition is build (called with @param Epub &$epub, @param Book &$book, @param Chapter &$chapter) */
    private ?\Closure $beforeBuild = null;


    /**
     * @param string fileName HTML source file
     */
    public function __construct(Book $book, string $fileName)
    {
        $this->book = $book;
        $this->fileName = $fileName;
        $this->parseHtml();
    }

    /**
     * Get chapter filename.
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Get chapter filename with full path.
     * @return string
     */
    public function getFullFileName(): string
    {
        return $this->book->getEpub()->getTempDir()
            . DIRECTORY_SEPARATOR . Epub::OEBPS
            . DIRECTORY_SEPARATOR . Epub::TEXTS
            . DIRECTORY_SEPARATOR . $this->getFileName();
    }

    /**
     * Wrap setter.
     * @param bool $wrap
     * @return Chapter
     */
    public function setWrap(bool $wrap): self
    {
        $this->wrap = $wrap;
        return $this;
    }

    /**
     * Wrap getter.
     * @return bool
     */
    public function getWrap(): bool
    {
        return $this->wrap;
    }

    /**
     * Chapter title setter.
     * @param string $title
     * @return Chapter
     */
    public function setTitle($title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Chapter title getter.
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Přidej do HTML odkaz na příslušný CSS soubor.
     * @param string $style
     * @return Chapter
     */
    public function addStyle($style): self
    {
        $this->styles[] = $style;
        return $this;
    }

    /**
     * Return known CSS files list.
     * @return string[]
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    public function addImage($image): self
    {
        $this->images[] = $image;
        return $this;
    }

    /**
     * Return known images list.
     * @return string[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Set method runned before epub build.
     * @param callable $function
     * @return Chapter
     */
    public function runBeforeBuild(callable $function): self
    {
        $this->beforeBuild = \Closure::fromCallable($function);
        return $this;
    }

    /**
     * Run function declared by "runBeforeBuild" (if any) and run "before" functions on each chapter.
     */
    public function manageBeforeFunction(Epub $epub, Book $book): void
    {
        if (\is_callable($this->beforeBuild)) {
            call_user_func($this->beforeBuild, $epub, $book, $this);
        }
    }

    /**
     * Parse HTML and find IMGs.
     */
    private function parseHtml(): void
    {
        $fullName = $this->getFullFileName();
        $doc = new \DOMDocument();
        if (!$doc->loadHTMLFile($fullName)) {
            throw new EpubException("Can't load HTML ({$fullName}).");
        }
        foreach ($doc->getElementsByTagName('img') as $item) {
            $src = $item->getAttribute('src');
            if ($src) {
                $this->addImage($src);
            }
        }
    }

}
