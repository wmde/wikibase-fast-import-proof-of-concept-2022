<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Api;

use ApiMain;
use Wikibase\Lib\SettingsArray;
use ApiBase;
use User;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 */
class FastImport extends ApiBase {
        protected $importSecret;

        public function __construct(
                ApiMain $mainModule,
                string $moduleName,
                SettingsArray $settings
        ) {
                parent::__construct( $mainModule, $moduleName );
                $this->importSecret = $settings->getSetting( 'importSecret' );
        }

        public static function factory(
                ApiMain $mainModule,
                string $moduleName,
                SettingsArray $settings
        ): self {
                return new self(
                        $mainModule,
                        $moduleName,
                        $settings
                );
        }

        public function isWriteMode(): bool {
                return true;
        }

        public function execute(): void {
                $params = $this->extractRequestParams();

                if( !isset( $params['data'] ) ) {
                        die('must provide data');
                }
                if( !isset( $params['secret'] ) ) {
                        die('must provide secret');
                }

                if( !$this->importSecret || !strlen( $this->importSecret ) >= 32 ) {
                        die('invalid secret configured');
                }
                if( $params['secret'] !== $this->importSecret ) {
                        die('invalid secret provided');
                }

                $user = User::newSystemUser( 'Wikibase Fast Import User (alpha)', [ 'steal' => true ] );
                $deserializer = WikibaseRepo::getAllTypesEntityDeserializer();
                $store = WikibaseRepo::getEntityStore();

                $results = [];
                foreach ( json_decode( $params['data'], true ) as $key => $possibleEntity ) {
                        try{
                                $entity = $deserializer->deserialize($possibleEntity);
                                $revision = $store->saveEntity(
                                        $entity,
                                        'Fast import',
                                        $user,
                                        EDIT_NEW,
                                        false,
                                        []
                                );
                                $results[$key] = [
                                        'id' => $revision->getEntity()->getId()->getSerialization(),
                                        'rev' => $revision->getRevisionId(),
                                ];
                        } catch ( \Exception $e ) {
                                $results[$key] = [
                                        'error' => $e->getMessage(),
                                ];
                        }
                }

                $this->getResult()->addValue( null, 'results', $results );
        }

        protected function getAllowedParams(): array {
                return [
                        'data' => [
                                self::PARAM_TYPE => 'text',
                                self::PARAM_REQUIRED => true,
                        ],
                        'secret' => [
                                self::PARAM_TYPE => 'text',
                                self::PARAM_REQUIRED => true,
                        ]
                ];
        }
}
