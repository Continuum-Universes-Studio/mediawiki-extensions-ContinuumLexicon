<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Html\Html as MWHtml;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialLanguages extends SpecialPage {
    public function __construct() {
        parent::__construct( 'Languages', 'continuumlanguages-edit' );
    }

    public function doesWrites(): bool {
        return true;
    }

    protected function getGroupName(): string {
        return 'poweredbycontinuum';
    }

    public function execute( $subPage ): void {
        $this->checkPermissions();
        $this->setHeaders();

        $out = $this->getOutput();
        $request = $this->getRequest();
        $store = new LanguageStore();

        if ( $this->handleDeleteRequest( $store ) ) {
            return;
        }

        $out->addWikiMsg( 'continuumlanguages-summary' );
        $this->addNoticeFromRequest();

        $languageId = max( 0, $request->getInt( 'id', 0 ) );
        $isEdit = $request->getVal( 'action' ) === 'edit' && $languageId > 0;
        $language = $isEdit ? $store->getById( $languageId ) : null;

        if ( $isEdit && !$language ) {
            $out->addHTML( $this->buildMessageBox(
                'mw-message-box-warning',
                $this->msg( 'continuumlanguages-missing' )->text()
            ) );
            $isEdit = false;
        }

        $out->addHTML( MWHtml::element(
            'h2',
            [],
            $this->msg(
                $isEdit ? 'continuumlanguages-edit-heading' : 'continuumlanguages-create-heading'
            )->text()
        ) );

        if ( $isEdit ) {
            $out->addHTML(
                MWHtml::rawElement(
                    'p',
                    [],
                    $this->getLinkRenderer()->makeLink(
                        $this->getPageTitle(),
                        $this->msg( 'continuumlanguages-create-link' )->text()
                    )
                )
            );
        }

        $form = $this->buildLanguageForm( $store, $language );
        $result = $form->show();
        if ( $result === true || ( $result instanceof \Status && $result->isGood() ) ) {
            $out->redirect( $this->getPageTitle()->getLocalURL( [
                'notice' => $isEdit ? 'updated' : 'created',
            ] ) );
            return;
        }

        $out->addHTML( MWHtml::element( 'h2', [], $this->msg( 'continuumlanguages-list-heading' )->text() ) );
        $out->addHTML( $this->buildLanguagesTable( $store->getAll() ) );
    }

    private function buildLanguageForm( LanguageStore $store, ?Language $language ): HTMLForm {
        $parentOptions = [
            $this->msg( 'continuum-common-none' )->text() => '',
        ] + $store->getOptions( $language?->id );

        $formDescriptor = [
            'key' => [
                'type' => 'text',
                'label-message' => 'continuumlanguages-key',
                'help-message' => 'continuumlanguages-key-help',
                'required' => true,
                'maxlength' => 64,
                'default' => $language?->key ?? '',
                'validation-callback' => function ( $value ) use ( $store, $language ) {
                    $key = trim( (string)$value );
                    if ( !preg_match( '/^[a-z0-9_]+$/', $key ) ) {
                        return $this->msg( 'continuumlanguages-key-invalid' )->text();
                    }

                    if ( $store->keyExists( $key, $language?->id ) ) {
                        return $this->msg( 'continuumlanguages-key-duplicate' )->text();
                    }

                    return true;
                },
            ],
            'display_name' => [
                'type' => 'text',
                'label-message' => 'continuumlanguages-display-name',
                'required' => true,
                'maxlength' => 255,
                'default' => $language?->displayName ?? '',
            ],
            'parent_id' => [
                'type' => 'select',
                'label-message' => 'continuumlanguages-parent',
                'options' => $parentOptions,
                'default' => $language?->parentId !== null ? (string)$language->parentId : '',
                'validation-callback' => function ( $value ) use ( $store, $language ) {
                    if ( $value === '' || $value === null ) {
                        return true;
                    }

                    $parentId = (int)$value;
                    if ( $language !== null && $parentId === $language->id ) {
                        return $this->msg( 'continuumlanguages-parent-self' )->text();
                    }

                    if ( !$store->getById( $parentId ) ) {
                        return $this->msg( 'continuumlanguages-parent-invalid' )->text();
                    }

                    return true;
                },
            ],
            'stage' => [
                'type' => 'text',
                'label-message' => 'continuumlanguages-stage',
                'maxlength' => 64,
                'default' => $language?->stage ?? '',
            ],
            'notes' => [
                'type' => 'textarea',
                'label-message' => 'continuumlanguages-notes',
                'default' => $language?->notes ?? '',
                'rows' => 6,
            ],
        ];

        $form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'continuumlanguages-form' );
        $form->setSubmitTextMsg( $language ? 'savechanges' : 'create' );
        $form->setSubmitCallback( function ( array $data ) use ( $store, $language ) {
            $record = new Language(
                id: $language?->id,
                key: trim( (string)$data['key'] ),
                displayName: trim( (string)$data['display_name'] ),
                parentId: $data['parent_id'] !== '' ? (int)$data['parent_id'] : null,
                stage: $this->normalizeNullable( $data['stage'] ?? null ),
                notes: $this->normalizeNullable( $data['notes'] ?? null ),
            );

            if ( $language ) {
                $store->update( $language->id, $record );
            } else {
                $store->insert( $record );
            }

            return true;
        } );

        return $form;
    }

    private function buildLanguagesTable( array $languages ): string {
        if ( !$languages ) {
            return $this->buildMessageBox(
                'mw-message-box-notice',
                $this->msg( 'continuumlanguages-noresults' )->text()
            );
        }

        $rows = '';
        foreach ( $languages as $language ) {
            $editLink = $this->getLinkRenderer()->makeLink(
                $this->getPageTitle(),
                $this->msg( 'edit' )->text(),
                [],
                [ 'action' => 'edit', 'id' => $language->id ]
            );

            $rows .= MWHtml::rawElement(
                'tr',
                [],
                MWHtml::element( 'td', [], $language->displayName ) .
                MWHtml::element( 'td', [], $language->key ) .
                MWHtml::element(
                    'td',
                    [],
                    $language->parentDisplayName ?? $this->msg( 'continuum-common-none' )->text()
                ) .
                MWHtml::element( 'td', [], $language->stage ?? '' ) .
                MWHtml::rawElement(
                    'td',
                    [],
                    $editLink . ' ' . $this->buildDeleteForm( 'delete-language', $language->id )
                )
            );
        }

        $header = MWHtml::rawElement(
            'tr',
            [],
            MWHtml::element( 'th', [], $this->msg( 'continuumlanguages-display-name' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlanguages-key' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlanguages-parent' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlanguages-stage' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuum-common-actions' )->text() )
        );

        return MWHtml::rawElement(
            'table',
            [ 'class' => 'wikitable sortable' ],
            $header . $rows
        );
    }

    private function buildDeleteForm( string $action, int $id ): string {
        $html = MWHtml::openElement( 'form', [
            'method' => 'post',
            'action' => $this->getPageTitle()->getLocalURL(),
            'style' => 'display:inline-block'
        ] );
        $html .= MWHtml::hidden( 'continuum_action', $action );
        $html .= MWHtml::hidden( 'id', (string)$id );
        $html .= MWHtml::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
        $html .= MWHtml::submitButton(
            $this->msg( 'delete' )->text(),
            [ 'class' => 'mw-ui-destructive' ]
        );
        $html .= MWHtml::closeElement( 'form' );

        return $html;
    }

    private function handleDeleteRequest( LanguageStore $store ): bool {
        $request = $this->getRequest();
        if ( !$request->wasPosted() || $request->getVal( 'continuum_action' ) !== 'delete-language' ) {
            return false;
        }

        if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-error',
                $this->msg( 'sessionfailure' )->text()
            ) );
            return false;
        }

        $languageId = max( 0, $request->getInt( 'id', 0 ) );
        if ( $languageId < 1 || !$store->getById( $languageId ) ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-warning',
                $this->msg( 'continuumlanguages-missing' )->text()
            ) );
            return false;
        }

        $store->delete( $languageId );
        $this->getOutput()->redirect( $this->getPageTitle()->getLocalURL( [ 'notice' => 'deleted' ] ) );
        return true;
    }

    private function addNoticeFromRequest(): void {
        $notice = $this->getRequest()->getVal( 'notice', '' );
        if ( $notice === '' ) {
            return;
        }

        $messageKey = match ( $notice ) {
            'created' => 'continuumlanguages-created',
            'updated' => 'continuumlanguages-updated',
            'deleted' => 'continuumlanguages-deleted',
            default => null,
        };

        if ( $messageKey !== null ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-success',
                $this->msg( $messageKey )->text()
            ) );
        }
    }

    private function normalizeNullable( mixed $value ): ?string {
        $value = trim( (string)$value );
        return $value !== '' ? $value : null;
    }

    private function buildMessageBox( string $class, string $message ): string {
        return MWHtml::element( 'p', [ 'class' => "mw-message-box $class" ], $message );
    }
}
