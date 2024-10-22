<?php
namespace Spaceboy\Epub;

use Spaceboy\Epub\Exception;
use Spaceboy\Epub\Epub;
use Spaceboy\Epub\Chapter;

class Book {

    /** @var */
    //private $activeChapter;

    /** @var Epub */
    private $epub;

    /** @var int */
    private $bookNo;

    /** @var string */
    private $bookTitle;

    /** @var Chapter[] */
    private $chapters   = [];

    /** @var \Closure|null method called before publacition is build (called with @param Epub &$epub, @param Book &$book) */
    private ?\Closure $beforeBuild = null;


    /**
     * Class constructor.
     * @param Epub $epub parent
     * @param integer $bookNo book number
     * @param string $title book title
     */
    public function __construct($epub, $bookNo, $bookTitle)
    {
        $this->epub         = $epub;
        $this->bookNo       = $bookNo;
        $this->bookTitle    = $bookTitle;
    }

    /**
     * @return integer
     */
    public function getNumber()
    {
        return $this->bookNo;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->bookTitle;
    }

    /**
     * Zjisti název kapitoly (základní jméno souboru).
     * @param string $name
     * @return string
     */
    private function getChapterName($name)
    {
        return (
            $name
            ? $name
            : sprintf("b%'02dc%'03d.html", $this->bookNo, count($this->chapters) + 1)
        );
    }

    /**
     * Add chapter (from HTML code).
     * @param string html
     * @param string name
     * @return Chapter
     */
    public function addChapterHtml($html, $name = NULL)
    {
        $newFileName = $this->getChapterName($name);
        if (!file_put_contents(
            $this->epub->getTempDir() . DIRECTORY_SEPARATOR . Epub::OEBPS . DIRECTORY_SEPARATOR . Epub::TEXTS . DIRECTORY_SEPARATOR . $newFileName,
            $html
        )) {
            throw new EpubException("Can not create chapter file in temporary directory.");
        }
        return $this->chapters[$newFileName] = new Chapter($this, $newFileName);
    }

    /**
     * Add chapter (from file).
     * @param string filename
     * @param integer order
     * @return Chapter
     */
    public function addChapterFile($fileName, $name = NULL)
    {
        if (!is_file($fileName) || (!is_readable($fileName))) {
            throw new EpubException("Chapter is not file or is not readable ({$fileName}).");
        }
        $newFileName = $this->getChapterName($name);
        if (!copy($fileName, $this->epub->getTempDir() . DIRECTORY_SEPARATOR . Epub::OEBPS . DIRECTORY_SEPARATOR . Epub::TEXTS . DIRECTORY_SEPARATOR . $newFileName)) {
            throw new EpubException("Can not copy chapter to temporary directory ({$fileName}).");
        }
        return $this->chapters[$newFileName] = new Chapter($this, $newFileName);
    }

    /**
     * Return chapter list.
     * @return Chapter[]
     */
    public function getChapters()
    {
        return $this->chapters;
    }

    /**
     * Return epub.
     * @return Epub
     */
    public function getEpub(): Epub
    {
        return $this->epub;
    }

    /**
     * Set method runned before epub build.
     * @param callable $function
     * @return Book
     */
    public function runBeforeBuild(callable $function): self
    {
        $this->beforeBuild = \Closure::fromCallable($function);
        return $this;
    }

    /**
     * Run function declared by "runBeforeBuild" (if any) and run "before" functions on each chapter.
     */
    public function manageBeforeFunctions(Epub $epub): void
    {
        if (\is_callable($this->beforeBuild)) {
            call_user_func($this->beforeBuild, $this);
        }
        foreach ($this->chapters as $chapter) {
            $chapter->manageBeforeFunction($epub, $this);
        }
    }
}
