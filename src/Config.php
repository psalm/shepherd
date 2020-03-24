<?php

namespace Psalm\Shepherd;

abstract class Config
{
    /** @var ?string */
    public $gh_enterprise_url;

    /** @var null|Config\Custom|Config\OAuthApp */
    private static $config;

    /**
     * @var array{dsn: string, user: string, password: string}
     */
    public $mysql;

    /** @return Config\Custom|Config\OAuthApp */
    public static function getInstance() : Config
    {
        if (self::$config) {
            return self::$config;
        }

        $config_path = __DIR__ . '/../config.json';

        if (!file_exists($config_path)) {
          return self::loadConfigFromEnv();
        } else {
          return self::loadConfigFromFile($config_path);
        }


    }

    private static function loadConfigFromFile($config_path){
      /**
       * @var array{
       *     oauth_app?: array{
       *         client_id: string,
       *         client_secret: string
       *     },
       *     custom?: array{
       *         personal_token: string
       *     },
       *     host?: string,
       *     mysql: array{dsn: string, user: string, password: string}
       * }
       */
      $config = json_decode(file_get_contents($config_path), true);

      return self::initializeConfig($config);

    }
    
    /**
     * This is a crazy method meant to pull SHEPHERD_ vars from $_ENV
     * into a structure mirroring self::loadConfigFromFilie for use with
     * self::initializeConfig
     *
     * TODO: hopefully this entire class gets replaced by something like
     * https://github.com/vlucas/phpdotenv at some point.
     */
    private static function loadConfigFromEnv(){

      // initialize an empty config
      $config = array();

      // search each ENV
      foreach ( $_ENV as $var_name => $val ){
        // for shepherd vars
        if ( strpos( $var_name, 'SHEPHERD' ) === 0 ){
          $var_parts = explode( '_', $var_name, 2 );
          // save the suffix, we'll need it later to tell if its a config group, or root level config item
          $var_name_suffix = $var_parts[1];
          // this var is either a config group, or root level item
          $is_a_config_group = false;
          // check the suffix to see if it begins with any of these config group names
          foreach( ['CUSTOM', 'OAUTH_APP', 'MYSQL'] as $config_group ){
            if( strpos( $var_name_suffix, $config_group ) === 0 ){
              // remember that this var was a config group, so no need to set it as a root level item later
              $is_a_config_group = true;
              // set the item as a child of the config group in the $config structure initialized earlier
              $config[ strtolower( $config_group ) ][ strtolower( substr( $var_name_suffix, strlen($config_group) + 1 ) ) ] = $val;
            }
          }
          // if the item was not a config group item, set it as a root level item in $config
          if( $is_a_config_group === false ){
            $config[ strtolower( $var_name_suffix ) ] = $val;
          }
        }
      }

      // initialize the config with all the SHEPHERD_ vars we got from $_ENV
      return self::initializeConfig($config);

    }

    private static function initializeConfig($config){
      if (isset($config['custom']['personal_token'])) {
        return self::$config = new Config\Custom(
          $config['custom']['personal_token'],
          $config['custom']['webhook_secret'] ?? null,
          $config['gh_enterprise_url'] ?? null,
          $config['mysql']
        );
      }

      if (isset($config['oauth_app']['client_id']) && isset($config['oauth_app']['client_secret'])) {
        return self::$config = new Config\OAuthApp(
          $config['oauth_app']['client_id'],
          $config['oauth_app']['client_secret'],
          $config['gh_enterprise_url'] ?? null,
          $config['oauth_app']['public_access_oauth_token'] ?? null,
          $config['mysql']
        );
      }

      throw new \UnexpectedValueException('Invalid config');

    }
}
