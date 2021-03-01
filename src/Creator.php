<?php
namespace Spaceboy\Epub;


class Creator
{
    /** @var string role */
    private $role;

    /** @var string name */
    private $name;

    /** @var string nameFileAs */
    private $nameFileAs;

    /**
     * Class constructor.
     * @param string $role
     * @param string $name
     * @param string $nameFileAs
     */
    public function __construct($role, $name, $nameFileAs = NULL)
    {
        $this->role = $role;
        $this->name = $name;
        $this->nameFileAs = $nameFileAs;
    }

    /**
     * Vydej jméno autora (Jméno Příjmení).
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Vydej jméno autora (Příjmení, Jméno).
     * @return string
     */
    public function getNameFileAs()
    {
        return $this->nameFileAs;
    }

    /**
     * Vydej roli autora (aut, ill, ...).
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

}
