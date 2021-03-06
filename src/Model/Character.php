<?php

namespace loyen\DndbCharacterSheet\Model;

class Character implements \JsonSerializable
{
    private string $name;
    private array $abilityScores;
    private int $proficiencyBonus;
    private array $classes;
    private array $currencies;
    private array $movementSpeeds;
    private array $proficiencies;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setAbilityScores(array $abilityScores): void
    {
        $this->abilityScores = $abilityScores;
    }

    public function getAbilityScores(): array
    {
        return $this->abilityScores;
    }

    public function setClasses(array $classes): void
    {
        $this->classes = $classes;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function setCurrencies(array $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getCurrencies(): array
    {
        return $this->currencies;
    }

    public function setProficiencyBonus(int $proficiencyBonus): void
    {
        $this->proficiencyBonus = $proficiencyBonus;
    }

    public function getProficiencyBonus(): int
    {
        return $this->proficiencyBonus;
    }

    public function setMovementSpeeds(array $movementSpeeds): void
    {
        $this->movementSpeeds = $movementSpeeds;
    }

    public function getMovementSpeeds(): array
    {
        return $this->movementSpeeds;
    }

    public function setProficiencies(array $proficiencies): void
    {
        $this->proficiencies = $proficiencies;
    }

    public function getProficiencies(): array
    {
        return $this->proficiencies;
    }

    public function jsonSerialize(): mixed
    {
        return \get_object_vars($this);
    }
}
