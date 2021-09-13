<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$dca = &$GLOBALS['TL_DCA']['tl_settings'];

/**
 * Palettes
 */
$dca['palettes']['default'] .= ';{huh_google_maps_legend},googlemaps_apiKey;';

/**
 * Fields
 */
$fields = [
    'googlemaps_apiKey' => [
        'label'     => &$GLOBALS['TL_LANG']['tl_settings']['googlemaps_apiKey'],
        'exclude'   => true,
        'search'    => true,
        'inputType' => 'text',
        'eval'      => ['maxlength' => 255, 'tl_class' => 'w50']
    ]
];

$dca['fields'] = array_merge($dca['fields'], $fields);