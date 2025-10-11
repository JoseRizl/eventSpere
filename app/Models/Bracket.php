<?php

/**
 * Represents a tournament bracket for an event.
 */
class Bracket
{
    /**
     * @var string The unique identifier for the bracket.
     */
    public $id;

    /**
     * @var string The ID of the event this bracket belongs to.
     */
    public $event_id;

    /**
     * @var string The name or title of the bracket (e.g., "Men's Basketball - Finals").
     */
    public $name;

    /**
     * @var string The type of bracket (e.g., 'single_elimination', 'double_elimination', 'round_robin').
     */
    public $type;

    /**
     * @var array|null The structure of the bracket, often stored as JSON.
     * This would contain rounds, matches, and participants.
     */
    public $structure;

    /**
     * Bracket constructor.
     *
     * @param string $event_id The ID of the associated event.
     * @param string $name The name of the bracket.
     * @param string $type The type of the tournament bracket.
     * @param array|null $structure The initial structure of the bracket.
     */
    public function __construct($event_id, $name, $type, $structure = null)
    {
        $this->id = uniqid('bracket_', true);
        $this->event_id = $event_id;
        $this->name = $name;
        $this->type = $type;
        $this->structure = $structure ?? $this->generateInitialStructure();
    }

    /**
     * Generates an initial empty structure based on the bracket type.
     * This is a placeholder and would need more complex logic based on your needs.
     *
     * @return array An empty structure.
     */
    private function generateInitialStructure()
    {
        // Example for a single elimination bracket.
        // You would populate this with teams/participants.
        if ($this->type === 'single_elimination') {
            return [
                'rounds' => []
            ];
        }
        return [];
    }

    /**
     * A method to convert the object to an associative array.
     *
     * @return array
     */
    public function toArray() {
        return get_object_vars($this);
    }
}
