<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;
use MediaWiki\MediaWikiServices;
use DatabaseUpdater;

class Hooks {
    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
        $dir = dirname( __DIR__ );
        $updater->addExtensionTable(
            'continuum_lexicon',
            $dir . '/sql/tables.sql'
        );
        $updater->addExtensionTable(
            'continuum_languages',
            $dir . '/sql/continuum_languages.sql'
        );
        $updater->addExtensionField(
            'continuum_lexicon',
            'clx_language_id',
            $dir . '/sql/patch-add-clx_language_id.sql'
        );
        $updater->addExtensionIndex(
            'continuum_lexicon',
            'clx_language_id',
            $dir . '/sql/patch-add-clx_language_id-index.sql'
        );
    }
    public static function onRegistration(): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( !$config->get( 'ContinuumPoweredByBadges' ) ) {
			return;
		}


		$scriptPath = $GLOBALS['wgScriptPath'] ?? '';
		$base = rtrim( $scriptPath, '/' ) . '/extensions/ContinuumLexicon/resources/assets';

		$GLOBALS['wgFooterIcons']['poweredby'] ??= [];

		$GLOBALS['wgFooterIcons']['poweredby']['continuum-universes'] = [
			'src' => "$base/poweredby-continuum.svg",
			'url' => 'https://continuum-universes.com/',
			'alt' => 'Powered by Continuum',
		];
	}
}
