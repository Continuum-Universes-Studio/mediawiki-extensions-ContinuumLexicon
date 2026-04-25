<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use ApiBase;
use Wikimedia\ParamValidator\ParamValidator;

class ApiContinuumLexiconImport extends ApiBase {
    public function execute() {
        $user = $this->getUser();

        if ( !$user->isAllowed( 'continuumlexicon-import' ) ) {
            $this->dieWithError( 'continuumlexicon-import-denied', 'permissiondenied' );
        }

        $payload = $this->getParameter( 'payload' );
        $decoded = json_decode( $payload, true );

        if ( !is_array( $decoded ) || !isset( $decoded['entries'] ) || !is_array( $decoded['entries'] ) ) {
            $this->dieWithError( 'continuumlexicon-invalid-payload', 'invalidpayload' );
        }

        $store = new LexiconStore();
        $results = [];

        foreach ( $decoded['entries'] as $i => $row ) {
            if ( !is_array( $row ) || empty( $row['language'] ) || empty( $row['lemma'] ) ) {
                $results[] = [
                    'index' => $i,
                    'status' => 'skipped',
                    'reason' => 'missing language or lemma'
                ];
                continue;
            }

            $entry = new LexiconEntry(
                id: null,
                language: (string)$row['language'],
                lemma: (string)$row['lemma'],
                romanized: $row['romanized'] ?? null,
                ipa: $row['ipa'] ?? null,
                pos: $row['pos'] ?? null,
                gloss: $row['gloss'] ?? null,
                definition: $row['definition'] ?? null,
                etymology: $row['etymology'] ?? null,
                parentId: isset( $row['parent_id'] ) ? (int)$row['parent_id'] : null,
                evolutionProfile: $row['evolution_profile'] ?? null,
                notes: $row['notes'] ?? null,
                sourcePageId: isset( $row['source_page_id'] ) ? (int)$row['source_page_id'] : null
            );

            $id = $store->upsertByLanguageAndLemma( $entry );
            $results[] = [
                'index' => $i,
                'status' => 'ok',
                'id' => $id,
                'lemma' => $entry->lemma,
                'language' => $entry->language
            ];
        }

        $this->getResult()->addValue( null, $this->getModuleName(), [
            'count' => count( $results ),
            'results' => $results
        ] );
    }

    public function isWriteMode(): bool {
        return true;
    }

    public function needsToken(): string {
        return 'csrf';
    }

    public function getAllowedParams(): array {
        return [
            'payload' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true
            ]
        ];
    }
}
