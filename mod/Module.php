<?php
namespace Module\OAuth2Client;

use Poirot\Application\Interfaces\Sapi;
use Poirot\Ioc\Container;
use Poirot\Ioc\Container\BuildContainer;
use Poirot\Std\Interfaces\Struct\iDataEntity;


class Module implements Sapi\iSapiModule
    , Sapi\Module\Feature\iFeatureModuleMergeConfig
    , Sapi\Module\Feature\iFeatureModuleNestServices
{
    const CONF_KEY = 'module.oauth2client';


    /**
     * Register config key/value
     *
     * priority: 1000 D
     *
     * - you may return an array or Traversable
     *   that would be merge with config current data
     *
     * @param iDataEntity $config
     *
     * @return array|\Traversable
     */
    function initConfig(iDataEntity $config)
    {
        return \Poirot\Config\load(__DIR__ . '/../../config/mod-oauth2client');
    }

    /**
     * Get Nested Module Services
     *
     * it can be used to manipulate other registered services by modules
     * with passed Container instance as argument.
     *
     * priority not that serious
     *
     * @param Container $moduleContainer
     *
     * @return null|array|BuildContainer|\Traversable
     */
    function getServices(Container $moduleContainer = null)
    {
        $conf = \Poirot\Config\load(__DIR__ . '/../../config/mod-oauth2client.services');
        return $conf;
    }
}
