<?php
namespace Spaceboy\Epub;

use Spaceboy\Epub\Exception;

class Chapter {

    /** @var string chapter filename */
    private $fileName;

    /** @var string */
    private $title;

    /** @var bool */
    private bool $wrap = true;

    /** @var string[] list of styles */
    private array $styles;

    /** @var string[] list of images */
    private array $images;

    /**
     * @param string fileName HTML source file
     */
    public function __construct($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
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
     * Vrať seznam CSS souborů z HTML.
     * @return string[]
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    public function addImage($image): self
    {
        $this->images[] = $images;
        return $this;
    }

    /**
     * Vrať seznam obrázků souborů z HTML.
     * @return string[]
     */
    public function getImages(): array
    {
        return $this->images;
    }
}
