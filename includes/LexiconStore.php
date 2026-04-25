<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ILoadBalancer;

class LexiconStore {
    private IConnectionProvider|ILoadBalancer $dbProvider;

    public function __construct( IConnectionProvider|ILoadBalancer|null $dbProvider = null ) {
        $this->dbProvider = $dbProvider ?? MediaWikiServices::getInstance()->getConnectionProvider();
    }

    private function getReplica() {
        if ( $this->dbProvider instanceof IConnectionProvider ) {
            return $this->dbProvider->getReplicaDatabase();
        }
        return $this->dbProvider->getConnection( DB_REPLICA );
    }

    private function getPrimary() {
        if ( $this->dbProvider instanceof IConnectionProvider ) {
            return $this->dbProvider->getPrimaryDatabase();
        }
        return $this->dbProvider->getConnection( DB_PRIMARY );
    }

    private function getSelectFields(): array {
        return [
            'clx_id',
            'clx_language',
            'clx_language_id',
            'clx_lemma',
            'clx_romanized',
            'clx_ipa',
            'clx_pos',
            'clx_gloss',
            'clx_definition',
            'clx_etymology',
            'clx_parent_id',
            'clx_evolution_profile',
            'clx_notes',
            'clx_source_page_id',
            'clx_created_at',
            'clx_updated_at',
            'language_display_name' => 'lang.cl_display_name',
        ];
    }

    public function insert( LexiconEntry $entry ): int {
        $dbw = $this->getPrimary();
        $timestamp = $dbw->timestamp();
        $dbw->newInsertQueryBuilder()
            ->insertInto( 'continuum_lexicon' )
            ->row( $entry->toInsertRow( $timestamp ) )
            ->caller( __METHOD__ )
            ->execute();

        return (int)$dbw->insertId();
    }

    public function update( int $id, LexiconEntry $entry ): void {
        $dbw = $this->getPrimary();
        $timestamp = $dbw->timestamp();
        $dbw->newUpdateQueryBuilder()
            ->update( 'continuum_lexicon' )
            ->set( $entry->toUpdateRow( $timestamp ) )
            ->where( [ 'clx_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();
    }

    public function upsertByLanguageAndLemma( LexiconEntry $entry ): int {
        $existing = $entry->languageId !== null
            ? $this->findOneByLanguageIdAndLemma( $entry->languageId, $entry->lemma )
            : null;

        if ( !$existing ) {
            $existing = $this->findOneByLanguageAndLemma( $entry->language, $entry->lemma );
        }

        if ( $existing ) {
            $this->update( $existing->id, $entry );
            return $existing->id;
        }
        return $this->insert( $entry );
    }

    public function getById( int $id ): ?LexiconEntry {
        $dbr = $this->getReplica();
        $row = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_lexicon' )
            ->leftJoin( 'continuum_languages', 'lang', 'lang.cl_id = clx_language_id' )
            ->where( [ 'clx_id' => $id ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? LexiconEntry::fromRow( $row ) : null;
    }

    public function findOneByLanguageAndLemma( string $language, string $lemma ): ?LexiconEntry {
        $dbr = $this->getReplica();
        $row = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_lexicon' )
            ->leftJoin( 'continuum_languages', 'lang', 'lang.cl_id = clx_language_id' )
            ->where( [
                'clx_language' => $language,
                'clx_lemma' => $lemma
            ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? LexiconEntry::fromRow( $row ) : null;
    }

    public function findOneByLanguageIdAndLemma( int $languageId, string $lemma ): ?LexiconEntry {
        $dbr = $this->getReplica();
        $row = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_lexicon' )
            ->leftJoin( 'continuum_languages', 'lang', 'lang.cl_id = clx_language_id' )
            ->where( [
                'clx_language_id' => $languageId,
                'clx_lemma' => $lemma,
            ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? LexiconEntry::fromRow( $row ) : null;
    }

    public function search(
        ?string $language = null,
        ?string $pos = null,
        ?string $search = null,
        int $limit = 100,
        int $offset = 0,
        ?int $languageId = null
    ): array {
        $dbr = $this->getReplica();
        $qb = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_lexicon' )
            ->leftJoin( 'continuum_languages', 'lang', 'lang.cl_id = clx_language_id' )
            ->orderBy( 'clx_lemma', 'ASC' )
            ->limit( $limit )
            ->offset( $offset )
            ->caller( __METHOD__ );

        $conds = [];

        if ( $languageId !== null ) {
            $conds['clx_language_id'] = $languageId;
        } elseif ( $language !== null && $language !== '' ) {
            $conds['clx_language'] = $language;
        }

        if ( $pos !== null && $pos !== '' ) {
            $conds['clx_pos'] = $pos;
        }

        if ( $conds ) {
            $qb->where( $conds );
        }

        if ( $search !== null && $search !== '' ) {
            $like = $dbr->buildLike( $dbr->anyString(), $search, $dbr->anyString() );
            $qb->andWhere( $dbr->orExpr( [
                $dbr->expr( 'clx_lemma', 'LIKE', $like ),
                $dbr->expr( 'clx_gloss', 'LIKE', $like ),
                $dbr->expr( 'clx_romanized', 'LIKE', $like ),
            ] ) );
        }

        $res = $qb->fetchResultSet();
        $entries = [];
        foreach ( $res as $row ) {
            $entries[] = LexiconEntry::fromRow( $row );
        }
        return $entries;
    }

    public function delete( int $id ): void {
        $dbw = $this->getPrimary();
        $dbw->startAtomic( __METHOD__ );

        $dbw->newUpdateQueryBuilder()
            ->update( 'continuum_lexicon' )
            ->set( [ 'clx_parent_id' => null ] )
            ->where( [ 'clx_parent_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        $dbw->newDeleteQueryBuilder()
            ->deleteFrom( 'continuum_lexicon' )
            ->where( [ 'clx_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        $dbw->endAtomic( __METHOD__ );
    }
}
