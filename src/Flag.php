<?php

/**
 * PHP Antimalware Scanner.
 *
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @copyright Copyright (c) 2019
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see https://github.com/marcocesarato/PHP-Antimalware-Scanner
 */

namespace marcocesarato\amwscan;

/**
 * Class Flag.
 */
class Flag
{
    /**
     * @var
     */
    public $name;
    /**
     * @var null
     */
    public $callback;
    /**
     * @var array
     */
    public $aliases = array();
    /**
     * @var bool
     */
    public $hasValue = false;
    /**
     * @var mixed
     */
    public $defaultValue;
    /**
     * @var mixed
     */
    public $var;
    /**
     * @var mixed
     */
    public $help;

    /**
     * Flag constructor.
     * @param $name
     * @param array $options
     * @param null $callback
     */
    public function __construct($name, $options = array(), $callback = null)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->aliases = array_merge(array("--$name"), (array)@$options['alias']);
        $this->defaultValue = @$options['default'];
        $this->hasValue = (bool)@$options['has_value'];
        $this->help = @$options['help'];
        if (array_key_exists('var', $options)) {
            $this->var = &$options['var'];
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $s = join('|', $this->aliases);
        if ($this->hasValue) {
            $s = "$s <{$this->name}>";
        }

        return "[$s]";
    }
}
