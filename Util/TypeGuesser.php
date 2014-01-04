<?php

namespace Playadz\Bundle\QuickSetupBundle\Util;


/**
 * Class TypeGuesser
 *
 * @package Playadz\Bundle\QuickSetupBundle\Util
 */
class TypeGuesser
{

    /**
     * http://symfony.com/doc/2.1/book/doctrine.html#doctrine-field-types-reference
     * @var array
     */
    static $doctrine2form_types = array(
        'string'        => 'text',
        'boolean'       => 'checkbox',
        'boolean'       => 'checkbox',
        'bigint'        => 'integer',
        'smallint'      => 'integer',
        'datetime'      => 'datetime',
        'date'          => 'date',
        'time'          => 'time',
        'text'          => 'text',
        'array'         => 'collection',
        'float'         => 'number',
        'decimal'       => 'number',
        'decimal'       => 'number',
    );

    /**
     * http://symfony.com/doc/2.1/book/forms.html
     * @var array
     */
    static protected $form2doctrine_types = array(
        "birthday"      => array('type' => 'date'),
        "checkbox"      => array('type' => 'boolean'),
        "choice"        => array('type' => 'array'),
        "collection"    => array('type' => 'array'),
        "country"       => array('type' => 'string', 'length' => '50'),
        "date"          => array('type' => 'date'),
        "datetime"      => array('type' => 'datetime'),
        "email"         => array('type' => 'string', 'length' => '50'),
        "hidden"        => array('type' => 'string', 'length' => '50'),
        "integer"       => array('type' => 'integer'),
        "language"      => array('type' => 'string', 'length' => '50'),
        "locale"        => array('type' => 'string', 'length' => '50'),
        "money"         => array('type' => 'decimal'),
        "number"        => array('type' => 'decimal'),
        "password"      => array('type' => 'string', 'length' => '255'),
        "percent"       => array('type' => 'decimal'),
        "radio"         => array('type' => 'text'),
        "repeated"      => array('type' => 'array'),
        "search"        => array('type' => 'string', 'length' => '100'),
        "textarea"      => array('type' => 'text'),
        "text"          => array('type' => 'text'),
        "time"          => array('type' => 'time'),
        "timezone"      => array('type' => 'time'),
        "url"           => array('type' => 'string', 'length' => '255'),
        "file"          => array('type' => 'string', 'length' => '255'),
    );

    // TODO
    static $additional_form_types = array(
        'phone'         => 'string(50)',
        'image'         => 'string(255)',
        'ip'            => 'string(50)',
        'zip',
        'address',
    );

    /**
     *
     * @param $type
     * @return string
     */
    static function getFormType($type)
    {
        if (preg_match('/(.*)\((.*)\)/', $type, $m))
        {
            $type = $m[1];
        }
        if (in_array($type, self::$form2doctrine_types)) return $type;
        if (isset(self::$doctrine2form_types[$type])) return self::$doctrine2form_types[$type];
        return $type;
    }
    /**
     *
     * @param $type
     * @return string
     */
    static function getEntityType($type)
    {
        // match string(250), integer(3)
        if (preg_match('/(.*)\((.*)\)/', $type, $m))
        {
            return array('type' => $m[1], 'length' => $m[2]);
        }
        else if (isset(self::$form2doctrine_types[$type]))
        {
            return self::$form2doctrine_types[$type];
        }
        else if (isset(self::$doctrine2form_types[$type]))
        {
            return array('type' => $type);
        }
        return array('type' => $type);
    }



}