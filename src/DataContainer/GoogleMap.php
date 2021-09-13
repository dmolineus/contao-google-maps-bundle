<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\GoogleMapsBundle\DataContainer;

use Contao\Backend;
use Contao\BackendUser;
use Contao\Database;
use Contao\DataContainer;
use HeimrichHannot\GoogleMapsBundle\Model\GoogleMapModel;
use Ivory\GoogleMap\Control\ControlPosition;
use Ivory\GoogleMap\Control\MapTypeControlStyle;
use Ivory\GoogleMap\MapTypeId;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GoogleMap extends Backend
{
    const SIZE_MODE_ASPECT_RATIO = 'aspect_ratio';
    const SIZE_MODE_STATIC = 'static';
    const SIZE_MODE_CSS = 'css';

    const SIZE_MODES = [
        self::SIZE_MODE_ASPECT_RATIO,
        self::SIZE_MODE_STATIC,
        self::SIZE_MODE_CSS,
    ];

    const MAP_TYPE_BASE = 'base';
    const MAP_TYPE_RESPONSIVE = 'responsive';

    const TYPES = [
        MapTypeId::ROADMAP,
        MapTypeId::SATELLITE,
        MapTypeId::TERRAIN,
        MapTypeId::HYBRID,
    ];

    const POSITIONING_MODE_STANDARD = 'standard';
    const POSITIONING_MODE_BOUND = 'bound';

    const POSITIONING_MODES = [
        self::POSITIONING_MODE_STANDARD,
        self::POSITIONING_MODE_BOUND,
    ];

    const BOUND_MODE_COORDINATES = 'coordinates';
    const BOUND_MODE_AUTOMATIC = 'automatic';

    const BOUND_MODES = [
        self::BOUND_MODE_COORDINATES,
        self::BOUND_MODE_AUTOMATIC,
    ];

    const CENTER_MODE_COORDINATE = 'coordinate';
    const CENTER_MODE_STATIC_ADDRESS = 'static_address';
    const CENTER_MODE_EXTERNAL = 'external';

    const CENTER_MODES = [
        self::CENTER_MODE_COORDINATE,
        self::CENTER_MODE_STATIC_ADDRESS,
        self::CENTER_MODE_EXTERNAL,
    ];

    const POSITIONS = [
        ControlPosition::TOP_LEFT,
        ControlPosition::TOP_CENTER,
        ControlPosition::TOP_RIGHT,
        ControlPosition::LEFT_TOP,
        'c1',
        ControlPosition::RIGHT_TOP,
        ControlPosition::LEFT_CENTER,
        'c2',
        ControlPosition::RIGHT_CENTER,
        ControlPosition::LEFT_BOTTOM,
        'c3',
        ControlPosition::RIGHT_BOTTOM,
        ControlPosition::BOTTOM_LEFT,
        ControlPosition::BOTTOM_CENTER,
        ControlPosition::BOTTOM_RIGHT,
    ];

    const MAP_CONTROL_STYLES = [
        MapTypeControlStyle::DEFAULT_,
        MapTypeControlStyle::DROPDOWN_MENU,
        MapTypeControlStyle::HORIZONTAL_BAR,
    ];

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    public function getResponsiveMaps(DataContainer $dc)
    {
        $options = [];

        /** @var GoogleMapModel $configAdapter */
        $configAdapter = $this->container->get('contao.framework')->getAdapter(GoogleMapModel::class);

        if (null === ($configs = $configAdapter->findBy(['type = ?'], 'responsive'))) {
            return $options;
        }

        return $configs->fetchEach('title');
    }

    public static function getMapChoices()
    {
        return \Contao\System::getContainer()->get('huh.utils.choice.model_instance')->getCachedChoices(
            [
                'dataContainer' => 'tl_google_map',
            ]
        );
    }

    public function checkPermission()
    {
        $user = BackendUser::getInstance();
        $database = Database::getInstance();

        if ($user->isAdmin) {
            return;
        }

        // Set root IDs
        if (!\is_array($user->contao_google_maps_bundles) || empty($user->contao_google_maps_bundles)) {
            $root = [0];
        } else {
            $root = $user->contao_google_maps_bundles;
        }

        $GLOBALS['TL_DCA']['tl_google_map']['list']['sorting']['root'] = $root;

        // Check permissions to add archives
        if (!$user->hasAccess('create', 'contao_google_maps_bundlep')) {
            $GLOBALS['TL_DCA']['tl_google_map']['config']['closed'] = true;
        }

        /** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
        $objSession = \Contao\System::getContainer()->get('session');

        // Check current action
        switch (\Contao\Input::get('act')) {
            case 'create':
            case 'select':
                // Allow
                break;

            case 'edit':
                // Dynamically add the record to the user profile
                if (!\in_array(\Contao\Input::get('id'), $root)) {
                    /** @var \Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface $sessionBag */
                    $sessionBag = $objSession->getBag('contao_backend');

                    $arrNew = $sessionBag->get('new_records');

                    if (\is_array($arrNew['tl_google_map']) && \in_array(\Contao\Input::get('id'), $arrNew['tl_google_map'])) {
                        // Add the permissions on group level
                        if ('custom' != $user->inherit) {
                            $objGroup = $database->execute(
                                'SELECT id, contao_google_maps_bundles, contao_google_maps_bundlep FROM tl_user_group WHERE id IN('.implode(
                                    ',',
                                    array_map(
                                        'intval',
                                        $user->groups
                                    )
                                ).')'
                            );

                            while ($objGroup->next()) {
                                $arrModulep = \StringUtil::deserialize($objGroup->contao_google_maps_bundlep);

                                if (\is_array($arrModulep) && \in_array('create', $arrModulep)) {
                                    $arrModules = (array) \StringUtil::deserialize($objGroup->contao_google_maps_bundles, true);
                                    $arrModules[] = \Contao\Input::get('id');

                                    $database->prepare('UPDATE tl_user_group SET contao_google_maps_bundles=? WHERE id=?')->execute(
                                        serialize($arrModules),
                                        $objGroup->id
                                    );
                                }
                            }
                        }

                        // Add the permissions on user level
                        if ('group' != $user->inherit) {
                            $user = $database->prepare('SELECT contao_google_maps_bundles, contao_google_maps_bundlep FROM tl_user WHERE id=?')
                                ->limit(1)
                                ->execute($user->id);

                            $arrModulep = \StringUtil::deserialize($user->contao_google_maps_bundlep);

                            if (\is_array($arrModulep) && \in_array('create', $arrModulep)) {
                                $arrModules = (array) \StringUtil::deserialize($user->contao_google_maps_bundles, true);
                                $arrModules[] = \Contao\Input::get('id');

                                $database->prepare('UPDATE tl_user SET contao_google_maps_bundles=? WHERE id=?')->execute(
                                    serialize($arrModules),
                                    $user->id
                                );
                            }
                        }

                        // Add the new element to the user object
                        $root[] = \Contao\Input::get('id');
                        $user->contao_google_maps_bundles = $root;
                    }
                }
            // no break;

            case 'copy':
            case 'delete':
            case 'show':
                if (!\in_array(\Contao\Input::get('id'), $root)
                    || ('delete' == \Contao\Input::get('act')
                        && !$user->hasAccess(
                            'delete',
                            'contao_google_maps_bundlep'
                        ))
                ) {
                    throw new \Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to '.\Contao\Input::get('act').' google_map ID '.\Contao\Input::get('id').'.');
                }

                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
                $session = $objSession->all();

                if ('deleteAll' == \Contao\Input::get('act') && !$user->hasAccess('delete', 'contao_google_maps_bundlep')) {
                    $session['CURRENT']['IDS'] = [];
                } else {
                    $session['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $root);
                }
                $objSession->replace($session);

                break;

            default:
                if (\strlen(\Contao\Input::get('act'))) {
                    throw new \Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to '.\Contao\Input::get('act').' google_maps.');
                }

                break;
        }
    }

    public function edit($row, $href, $label, $title, $icon, $attributes)
    {
        return $row['type'] !== static::MAP_TYPE_RESPONSIVE
            ? '<a href="'.$this->addToUrl(
                $href.'&amp;id='.$row['id']
            ).'" title="'.\StringUtil::specialchars($title).'"'.$attributes.'>'.\Image::getHtml($icon, $label).'</a> '
            : \Image::getHtml(
                preg_replace('/\.svg$/i', '_.svg', $icon)
            ).' ';
    }

    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        return \Contao\BackendUser::getInstance()->canEditFieldsOf('tl_google_map')
            ? '<a href="'.$this->addToUrl(
                $href.'&amp;id='.$row['id']
            ).'" title="'.\StringUtil::specialchars($title).'"'.$attributes.'>'.\Image::getHtml($icon, $label).'</a> '
            : \Image::getHtml(
                preg_replace('/\.svg$/i', '_.svg', $icon)
            ).' ';
    }

    public function copyArchive($row, $href, $label, $title, $icon, $attributes)
    {
        return \Contao\BackendUser::getInstance()->hasAccess('create', 'contao_google_maps_bundlep')
            ? '<a href="'.$this->addToUrl(
                $href.'&amp;id='.$row['id']
            ).'" title="'.\StringUtil::specialchars($title).'"'.$attributes.'>'.\Image::getHtml($icon, $label).'</a> '
            : \Image::getHtml(
                preg_replace('/\.svg$/i', '_.svg', $icon)
            ).' ';
    }

    public function deleteArchive($row, $href, $label, $title, $icon, $attributes)
    {
        return \Contao\BackendUser::getInstance()->hasAccess('delete', 'contao_google_maps_bundlep')
            ? '<a href="'.$this->addToUrl(
                $href.'&amp;id='.$row['id']
            ).'" title="'.\StringUtil::specialchars($title).'"'.$attributes.'>'.\Image::getHtml($icon, $label).'</a> '
            : \Image::getHtml(
                preg_replace('/\.svg$/i', '_.svg', $icon)
            ).' ';
    }
}
