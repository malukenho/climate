<?php

namespace JoeTannenbaum\CLImate;

class Style {

    /**
     * An array of the current styles applied
     *
     * @var array
     */

    protected $current = [];

	/**
	 * The available colors
	 *
	 * @var array
	 */

	protected $colors = [
        'default'       => 39,
        'black'         => 30,
        'red'           => 31,
        'green'         => 32,
        'yellow'        => 33,
        'blue'          => 34,
        'magenta'       => 35,
        'cyan'          => 36,
        'light_gray'    => 37,
        'dark_gray'     => 90,
        'light_red'     => 91,
        'light_green'   => 92,
        'light_yellow'  => 93,
        'light_blue'    => 94,
        'light_magenta' => 95,
        'light_cyan'    => 96,
        'white'         => 97,
	];

    /**
     * The available formatting options
     *
     * @var array
     */

    protected $formatting = [
        'bold'          => 1,
        'dim'           => 2,
        'underline'     => 4,
        'blink'         => 5,
        'invert'        => 7,
        'hidden'        => 8,
    ];

    /**
     * Commands that correspond to a color in the $colors property
     *
     * @var array
     */

    public $command_colors = [
        'info'    => 'green',
        'comment' => 'yellow',
        'whisper' => 'light_gray',
        'shout'   => 'red',
        'error'   => 'light_red',
    ];

    /**
     * An array of the tags that should be searched for
     *
     * @var array
     */

    public $tag_search  = [];

    /**
     * A corresponding array of the codes that should be
     * replaced with the $tag_search
     *
     * @var array
     */

    public $tag_replace = [];

    public function __construct()
    {
        $this->setDefaultStyles();
        $this->buildTags();
    }

    /**
     * Get the string that begins the style
     *
     * @param string $color
     * @return string
     */

    public function start( $style = NULL )
    {
        $style = $style ?: $this->currentStyleCode();

        return "\e[{$style}m";
    }

    /**
     * Get the string that ends the style
     *
     * @return string
     */

    public function end()
    {
        return "\e[0m";
    }

    /**
     * Retrieve the array of searchable tags
     *
     * @return array
     */

    public function tagSearch()
    {
        return $this->tag_search;
    }

    /**
     * Retrieve an array of tag replacements
     *
     * @return array
     */

    public function tagReplace()
    {
        $start_code = $this->start( $this->currentStyleCode() );

        return array_map( function ( $item ) use ( $start_code )
        {
            // We have to re-start the parent style after each tag is replaced
            if ( $item == $this->end() )
            {
                return $item . $start_code;
            }

            return $item;
        }, $this->tag_replace );
    }

    /**
     * Add a color to the available colors
     *
     * @param string $color
     * @param string $code
     */

    public function addColor( $color, $code )
    {
    	$this->colors[ $color ] = $code;

        $this->buildTags();
    }

    /**
     * Add a command with a color to the available commands
     *
     * @param string $command
     * @param string $color
     */

    public function addCommandColor( $command, $color )
    {
        if ( array_key_exists( $color, $this->colors ) )
        {
            $this->command_colors[ $command ] = $color;

            $this->buildTags();
        }
        else
        {
            throw new \Exception("The color '{$color}' for command '{$command}' is not defined.");
        }
    }

    /**
     * Set the current foreground color, optionally persisting it
     *
     * @param string $color
     * @param boolean $persistent
     */

    public function foreground( $color, $persistent = FALSE )
    {
        $this->current['foreground']['code'] = $this->getForeground( $color );

        if ( $persistent )
        {
            $this->setPersistent('foreground');
        }
    }

    /**
     * Set the current background color, optionally persisting it
     *
     * @param string $color
     * @param boolean $persistent
     */

    public function background( $color, $persistent = FALSE )
    {
        $this->current['background']['code'] = $this->getBackground( $color );

        if ( $persistent )
        {
            $this->setPersistent('background');
        }
    }

    /**
     * Set the current formatting, optionally persisting it
     *
     * @param string $format
     * @param boolean $persistent
     */

    public function formatting( $format, $persistent = FALSE )
    {
        $this->current['formatting']['code'][] = $this->getFormatting( $format );

        if ( $persistent )
        {
            $this->setPersistent('formatting');
        }
    }

    /**
     * Set the style property to be persistent
     *
     * @param string $type
     */

    public function setPersistent( $type )
    {
        $this->current[ $type ]['persistent'] = TRUE;
    }

    /**
     * Set all of the current properties to be consistent
     */

    public function persistent()
    {
        foreach ( $this->current as $type => $props )
        {
            $this->setPersistent( $type );
        }
    }

    /**
     * Reset all persistent properties
     */

    public function resetPersistent()
    {
        $this->setDefaultStyles();
    }

    /**
     * Reset current background
     *
     * @param boolean $persistent
     */

    public function resetBackground( $persistent = FALSE )
    {
        $this->resetProperty( 'background', $persistent );
    }

    /**
     * Reset current foreground
     *
     * @param boolean $persistent
     */

    public function resetForeground( $persistent = FALSE )
    {
        $this->resetProperty( 'foreground', $persistent );
    }

    /**
     * Reset current formatting
     *
     * @param boolean $persistent
     */

    public function resetFormatting( $persistent = FALSE )
    {
        $this->resetProperty( 'formatting', $persistent );
    }

    /**
     * Reset a given property back to its original values
     *
     * @param string $property
     * @param boolean $persistent
     */

    protected function resetProperty( $property, $persistent )
    {
        if ( !$this->current[ $property ]['persistent'] || $persistent )
        {
            $this->current[ $property ]['code'] = $this->getDefaultCode( $property );
        }
    }

    /**
     * Get the code for the foreground color
     *
     * @param string $color
     * @return string
     */

    public function getForeground( $color )
    {
        // If we already have the code, just return that
        if ( is_numeric( $color ) )
        {
            return $color;
        }

        // If it's a commmand color, do this function recursively with the color
        if ( $this->getCommandColor( $color ) )
        {
            return $this->getForeground( $this->getCommandColor( $color ) );
        }

        if ( array_key_exists( $color, $this->colors ) )
        {
            return $this->colors[ $color ];
        }

        return NULL;
    }

    /**
     * Get the code for the background color
     *
     * @param string $color
     * @return string
     */

    public function getBackground( $color )
    {
        // If we already have the code, just return that
        if ( is_numeric( $color ) )
        {
            return $color;
        }

        if ( array_key_exists( $color, $this->colors ) )
        {
            return $this->colors[ $color ] + 10;
        }

        return NULL;
    }

    /**
     * Get the code for the format
     *
     * @param string $format
     * @return string
     */

    public function getFormatting( $format )
    {
        // If we already have the code, just return that
        if ( is_numeric( $format ) )
        {
            return $format;
        }

        if ( array_key_exists( $format, $this->formatting ) )
        {
            return $this->formatting[ $format ];
        }

        return NULL;
    }

    /**
     * Get the color that corresponds to the command
     *
     * @param string $command
     * @return string
     */

    public function getCommandColor( $command )
    {
        if ( array_key_exists( $command, $this->command_colors ) )
        {
            return $this->command_colors[ $command ];
        }

        return NULL;
    }

    /**
     * Reset each current style property
     */

    public function reset()
    {
        foreach ( $this->current as $type => $prop )
        {
            $method = 'reset' . ucwords( $type );
            $this->$method();
        }
    }

    /**
     * Get a full list of the style options with their corresponding codes
     *
     * @return arrays
     */

    public function getMergedAttributes()
    {
        $styles = $this->colors;

        foreach ( $this->command_colors as $command => $color )
        {
            $styles[ $command ] = $this->getForeground( $color );
        }

        foreach ( $this->colors as $color => $code )
        {
            $styles[ 'background_' . $color ] = $code + 10;
        }

        foreach ( $this->formatting as $format => $code )
        {
            $styles[ $format ] = $code;
        }

        return $styles;
    }

    /**
     * Set default styles for each of the properties
     *
     * @return type
     */

    protected function setDefaultStyles()
    {
        $current_options = [ 'foreground', 'background', 'formatting' ];

        foreach ( $current_options as $option )
        {
            $this->current[ $option ] = [
                'code'       => $this->getDefaultCode( $option ),
                'persistent' => FALSE,
            ];
        }
    }

    /**
     * Get the default code for the given property
     *
     * @param string $property
     * @return mixed
     */

    protected function getDefaultCode( $property )
    {
        $default_code_method = 'default' . ucwords( $property ) . 'Code';

        if ( method_exists( $this, $default_code_method ) )
        {
            return $this->$default_code_method();
        }

        return NULL;
    }

    /**
     * Get the default formatting code
     *
     * @return array
     */

    protected function defaultFormattingCode()
    {
        return [];
    }

    /**
     * Build the search and replace for all of the various style tags
     */

    protected function buildTags()
    {
        $tags   = $this->getMergedAttributes();
        $search = [];

        foreach ( $tags as $tag => $color )
        {
            $search["<{$tag}>"]  = $this->start( $color );
            $search["</{$tag}>"] = $this->end();

            // Also replace JSONified end tags
            $search["<\\/{$tag}>"] = $this->end();
        }

        $this->tag_search  = array_keys( $search );
        $this->tag_replace = array_values( $search );
    }

    /**
     * Retrive the current style code
     *
     * @return string
     */

    protected function currentStyleCode()
    {
        $formats   = $this->current['formatting']['code'];

        $colors    = [
                        $this->current['foreground']['code'],
                        $this->current['background']['code'],
                    ];

        $style     = array_filter( array_merge( $formats, $colors ) );

        return implode( ';', $style );
    }

}