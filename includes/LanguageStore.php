<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ILoadBalancer;

class LanguageStore {
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
            'cl_id' => 'lang.cl_id',
            'cl_key' => 'lang.cl_key',
            'cl_display_name' => 'lang.cl_display_name',
            'cl_parent_id' => 'lang.cl_parent_id',
            'cl_stage' => 'lang.cl_stage',
            'cl_notes' => 'lang.cl_notes',
            'cl_created_at' => 'lang.cl_created_at',
            'cl_updated_at' => 'lang.cl_updated_at',
            'parent_display_name' => 'parent.cl_display_name',
        ];
    }

    public function getAll(): array {
        $dbr = $this->getReplica();
        $res = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_languages', 'lang' )
            ->leftJoin(
                'continuum_languages',
                'parent',
                'parent.cl_id = lang.cl_parent_id'
            )
            ->orderBy( 'lang.cl_display_name', 'ASC' )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        $languages = [];
        foreach ( $res as $row ) {
            $languages[] = Language::fromRow( $row );
        }

        return $languages;
    }

    public function getById( int $id ): ?Language {
        $dbr = $this->getReplica();
        $row = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_languages', 'lang' )
            ->leftJoin(
                'continuum_languages',
                'parent',
                'parent.cl_id = lang.cl_parent_id'
            )
            ->where( [ 'lang.cl_id' => $id ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? Language::fromRow( $row ) : null;
    }

    public function getByKey( string $key ): ?Language {
        $dbr = $this->getReplica();
        $row = $dbr->newSelectQueryBuilder()
            ->select( $this->getSelectFields() )
            ->from( 'continuum_languages', 'lang' )
            ->leftJoin(
                'continuum_languages',
                'parent',
                'parent.cl_id = lang.cl_parent_id'
            )
            ->where( [ 'lang.cl_key' => $key ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return $row ? Language::fromRow( $row ) : null;
    }

    public function keyExists( string $key, ?int $excludeId = null ): bool {
        $dbr = $this->getReplica();
        $qb = $dbr->newSelectQueryBuilder()
            ->select( [ 'lang.cl_id' ] )
            ->from( 'continuum_languages', 'lang' )
            ->where( [ 'lang.cl_key' => $key ] )
            ->caller( __METHOD__ );

        if ( $excludeId !== null ) {
            $qb->andWhere( $dbr->expr( 'lang.cl_id', '!=', $excludeId ) );
        }

        return (bool)$qb->fetchRow();
    }

    public function getOptions( ?int $excludeId = null ): array {
        $options = [];

        foreach ( $this->getAll() as $language ) {
            if ( $excludeId !== null && $language->id === $excludeId ) {
                continue;
            }

            $label = $language->displayName;
            if ( $language->stage !== null && $language->stage !== '' ) {
                $label .= ' (' . $language->stage . ')';
            }

            $options[$label] = (string)$language->id;
        }

        return $options;
    }

    public function insert( Language $language ): int {
        $dbw = $this->getPrimary();
        $timestamp = $dbw->timestamp();
        $dbw->newInsertQueryBuilder()
            ->insertInto( 'continuum_languages' )
            ->row( $language->toInsertRow( $timestamp ) )
            ->caller( __METHOD__ )
            ->execute();

        return (int)$dbw->insertId();
    }

    public function update( int $id, Language $language ): void {
        $dbw = $this->getPrimary();
        $timestamp = $dbw->timestamp();
        $existing = $this->getById( $id );

        $dbw->startAtomic( __METHOD__ );

        $dbw->newUpdateQueryBuilder()
            ->update( 'continuum_languages' )
            ->set( $language->toUpdateRow( $timestamp ) )
            ->where( [ 'cl_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        if ( $existing && $existing->displayName !== $language->displayName ) {
            $dbw->newUpdateQueryBuilder()
                ->update( 'continuum_lexicon' )
                ->set( [ 'clx_language' => $language->displayName ] )
                ->where( [ 'clx_language_id' => $id ] )
                ->caller( __METHOD__ )
                ->execute();
        }

        $dbw->endAtomic( __METHOD__ );
    }

    public function delete( int $id ): void {
        $dbw = $this->getPrimary();
        $dbw->startAtomic( __METHOD__ );

        $dbw->newUpdateQueryBuilder()
            ->update( 'continuum_languages' )
            ->set( [ 'cl_parent_id' => null ] )
            ->where( [ 'cl_parent_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        $dbw->newUpdateQueryBuilder()
            ->update( 'continuum_lexicon' )
            ->set( [ 'clx_language_id' => null ] )
            ->where( [ 'clx_language_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        $dbw->newDeleteQueryBuilder()
            ->deleteFrom( 'continuum_languages' )
            ->where( [ 'cl_id' => $id ] )
            ->caller( __METHOD__ )
            ->execute();

        $dbw->endAtomic( __METHOD__ );
    }
}
