<?php

class XmlEasyWriter
{
    private $xw;

    public function __construct($version = '1.0', $encoding = NULL, $standalone = NULL)
    {
        $xw = xmlwriter_open_memory();
        xmlwriter_set_indent($xw, 4);
        xmlwriter_set_indent_string($xw, '    ');
        xmlwriter_start_document($xw, $version, $encoding, $standalone);
        $this->xw = $xw;
    }

    /**
     * Open element
     */
    public function startElement($elementName, $attributes = NULL, $text = NULL)
    {
        $xw = $this->xw;
        xmlwriter_start_element($xw, $elementName);
        if (is_array($attributes)) {
            foreach ($attributes AS $key => $val) {
                xmlwriter_start_attribute($xw, $key);
                xmlwriter_text($xw, $val);
                xmlwriter_end_attribute($xw);
            }
        }
        if ($text) {
            xmlwriter_text($xw, $text);
        }
        return $this;
    }

    /**
     * Close element
     */
    public function endElement()
    {
        xmlwriter_end_element($this->xw);
        return $this;
    }

    /**
     * Insert closed element
     */
    public function insertElement($elementName, $attributes = NULL, $text = NULL)
    {
        return $this
            ->startElement($elementName, $attributes, $text)
            ->endElement();
    }

    /**
     * Insert comment
     */
    public function insertComment($comment)
    {
        xmlwriter_write_comment($this->xw, $comment);
        return $this;
    }

    /**
     * Insert CDATA
     */
    public function insertCData($elementName, $cData)
    {
        $xw = $this->xw;
        xmlwriter_start_element($xw, $elementName);
        xmlwriter_write_cdata($xw, $cdata);
        xmlwriter_end_element($xw);
        return $this;
    }

    /**
     * Insert processing instruction
     */
    public function insertPI($elementType, $code)
    {
        $xw = $this->xw;
        xmlwriter_start_pi($xw, $elementType);
        xmlwriter_text($xw, $code);
        xmlwriter_end_pi($xw);
        return $this;
    }

    public function endDocument()
    {
        xmlwriter_end_document($this->xw);
        return $this;
    }

    public function outputMemory()
    {
        return xmlwriter_output_memory($this->xw);
    }

}
