<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Html\Html as MWHtml;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialLexiconEdit extends SpecialPage {
    public function __construct() {
        parent::__construct( 'LexiconEdit', 'continuumlexicon-edit' );
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
        $config = $this->getConfig();
        $languageStore = new LanguageStore();
        $lexiconStore = new LexiconStore();

        if ( $this->handleDeleteRequest( $lexiconStore ) ) {
            return;
        }

        $out->addWikiMsg( 'continuumlexiconedit-summary' );
        $this->addNoticeFromRequest();

        $selectedLanguageId = max( 0, $request->getInt( 'language_id', 0 ) ) ?: null;
        $search = trim( $request->getVal( 'search', '' ) );
        $pos = trim( $request->getVal( 'pos', '' ) );
        $limit = max(
            1,
            min( 500, $request->getInt( 'limit', (int)$config->get( 'ContinuumLexiconDefaultLimit' ) ) )
        );

        $out->addHTML( $this->buildFilterForm( $languageStore, $selectedLanguageId, $search, $pos, $limit ) );

        $entryId = max( 0, $request->getInt( 'id', 0 ) );
        $isEdit = $request->getVal( 'action' ) === 'edit' && $entryId > 0;
        $entry = $isEdit ? $lexiconStore->getById( $entryId ) : null;

        if ( $isEdit && !$entry ) {
            $out->addHTML( $this->buildMessageBox(
                'mw-message-box-warning',
                $this->msg( 'continuumlexiconedit-missing' )->text()
            ) );
            $isEdit = false;
        }

        $languageOptions = $languageStore->getOptions();
        if ( !$languageOptions ) {
            $out->addWikiMsg( 'continuumlexiconedit-no-languages' );
        } else {
            $out->addHTML( MWHtml::element(
                'h2',
                [],
                $this->msg(
                    $isEdit ? 'continuumlexiconedit-edit-heading' : 'continuumlexiconedit-create-heading'
                )->text()
            ) );

            if ( $isEdit ) {
                $out->addHTML(
                    MWHtml::rawElement(
                        'p',
                        [],
                        $this->getLinkRenderer()->makeLink(
                            $this->getPageTitle(),
                            $this->msg( 'continuumlexiconedit-create-link' )->text(),
                        )
                    )
                );

                if ( $entry && $entry->languageId === null && $entry->language !== '' ) {
                    $out->addHTML( $this->buildMessageBox(
                        'mw-message-box-notice',
                        $this->msg( 'continuumlexiconedit-legacy-language', $entry->language )->text()
                    ) );
                }
            }

            $form = $this->buildEntryForm( $lexiconStore, $languageStore, $entry );
            $result = $form->show();
            if ( $result === true || ( $result instanceof \Status && $result->isGood() ) ) {
                $out->redirect( $this->getPageTitle()->getLocalURL( [
                    'notice' => $isEdit ? 'updated' : 'created',
                ] ) );
                return;
            }
        }

        $out->addHTML( MWHtml::element( 'h2', [], $this->msg( 'continuumlexiconedit-list-heading' )->text() ) );
        $entries = $lexiconStore->search(
            null,
            $pos !== '' ? $pos : null,
            $search !== '' ? $search : null,
            $limit,
            0,
            $selectedLanguageId
        );
        $out->addHTML( $this->buildEntriesTable( $entries ) );
    }

    private function buildFilterForm(
        LanguageStore $languageStore,
        ?int $selectedLanguageId,
        string $search,
        string $pos,
        int $limit
    ): string {
        $languageOptions = [
            $this->msg( 'continuumlexiconedit-all-languages' )->text() => '',
        ] + $languageStore->getOptions();

        $title = $this->getPageTitle();
        $html = MWHtml::openElement( 'form', [
            'method' => 'get',
            'action' => $title->getLocalURL()
        ] );
        $html .= MWHtml::hidden( 'title', $title->getPrefixedText() );
        $html .= '<fieldset><legend>' . $this->msg( 'continuumlexicon-filter' )->escaped() . '</legend>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-language' )->text(), 'clx-language-id' ) . ' ';
        $html .= $this->buildSelect(
            'language_id',
            'clx-language-id',
            $languageOptions,
            $selectedLanguageId !== null ? (string)$selectedLanguageId : ''
        );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-search' )->text(), 'clx-search' ) . ' ';
        $html .= MWHtml::input( 'search', $search, 'text', [ 'id' => 'clx-search' ] );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-pos' )->text(), 'clx-pos' ) . ' ';
        $html .= MWHtml::input( 'pos', $pos, 'text', [ 'id' => 'clx-pos' ] );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( 'Limit', 'clx-limit' ) . ' ';
        $html .= MWHtml::input(
            'limit',
            (string)$limit,
            'number',
            [ 'id' => 'clx-limit', 'min' => 1, 'max' => 500 ]
        );
        $html .= '</div>';

        $html .= MWHtml::submitButton( $this->msg( 'continuumlexicon-filter' )->text() );
        $html .= '</fieldset>';
        $html .= MWHtml::closeElement( 'form' );

        return $html;
    }

    private function buildEntryForm(
        LexiconStore $lexiconStore,
        LanguageStore $languageStore,
        ?LexiconEntry $entry
    ): HTMLForm {
        $languageOptions = [
            $this->msg( 'continuumlexiconedit-select-language' )->text() => '',
        ] + $languageStore->getOptions();

        $formDescriptor = [
            'language_id' => [
                'type' => 'select',
                'label-message' => 'continuumlexicon-language',
                'required' => true,
                'options' => $languageOptions,
                'default' => $entry?->languageId !== null ? (string)$entry->languageId : '',
                'validation-callback' => function ( $value ) use ( $languageStore ) {
                    if ( $value === '' || $value === null ) {
                        return $this->msg( 'continuumlexiconedit-language-required' )->text();
                    }

                    if ( !$languageStore->getById( (int)$value ) ) {
                        return $this->msg( 'continuumlexiconedit-language-invalid' )->text();
                    }

                    return true;
                },
            ],
            'lemma' => [
                'type' => 'text',
                'label-message' => 'continuumlexicon-lemma',
                'required' => true,
                'maxlength' => 255,
                'default' => $entry?->lemma ?? '',
            ],
            'romanized' => [
                'type' => 'text',
                'label-message' => 'continuumlexicon-romanized',
                'maxlength' => 255,
                'default' => $entry?->romanized ?? '',
            ],
            'ipa' => [
                'type' => 'text',
                'label-message' => 'continuumlexicon-ipa',
                'maxlength' => 255,
                'default' => $entry?->ipa ?? '',
            ],
            'pos' => [
                'type' => 'text',
                'label-message' => 'continuumlexicon-pos',
                'maxlength' => 64,
                'default' => $entry?->pos ?? '',
            ],
            'gloss' => [
                'type' => 'text',
                'label-message' => 'continuumlexicon-gloss',
                'maxlength' => 255,
                'default' => $entry?->gloss ?? '',
            ],
            'definition' => [
                'type' => 'textarea',
                'label-message' => 'continuumlexicon-definition',
                'rows' => 4,
                'default' => $entry?->definition ?? '',
            ],
            'etymology' => [
                'type' => 'textarea',
                'label-message' => 'continuumlexicon-etymology',
                'rows' => 4,
                'default' => $entry?->etymology ?? '',
            ],
            'parent_id' => [
                'type' => 'text',
                'label-message' => 'continuumlexiconedit-parent-entry-id',
                'default' => $entry?->parentId !== null ? (string)$entry->parentId : '',
                'validation-callback' => function ( $value ) use ( $lexiconStore, $entry ) {
                    $rawValue = trim( (string)$value );
                    if ( $rawValue === '' ) {
                        return true;
                    }

                    if ( !ctype_digit( $rawValue ) ) {
                        return $this->msg( 'continuumlexiconedit-parent-invalid' )->text();
                    }

                    $parentId = (int)$rawValue;
                    if ( $entry !== null && $parentId === $entry->id ) {
                        return $this->msg( 'continuumlexiconedit-parent-self' )->text();
                    }

                    if ( !$lexiconStore->getById( $parentId ) ) {
                        return $this->msg( 'continuumlexiconedit-parent-invalid' )->text();
                    }

                    return true;
                },
            ],
            'notes' => [
                'type' => 'textarea',
                'label-message' => 'continuumlexicon-notes',
                'rows' => 4,
                'default' => $entry?->notes ?? '',
            ],
        ];

        $form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'continuumlexiconedit-form' );
        $form->setSubmitTextMsg( $entry ? 'savechanges' : 'create' );
        $form->setSubmitCallback( function ( array $data ) use ( $lexiconStore, $languageStore, $entry ) {
            $language = $languageStore->getById( (int)$data['language_id'] );
            if ( !$language ) {
                return $this->msg( 'continuumlexiconedit-language-invalid' )->text();
            }

            $record = new LexiconEntry(
                id: $entry?->id,
                language: $language->displayName,
                lemma: trim( (string)$data['lemma'] ),
                languageId: $language->id,
                romanized: $this->normalizeNullable( $data['romanized'] ?? null ),
                ipa: $this->normalizeNullable( $data['ipa'] ?? null ),
                pos: $this->normalizeNullable( $data['pos'] ?? null ),
                gloss: $this->normalizeNullable( $data['gloss'] ?? null ),
                definition: $this->normalizeNullable( $data['definition'] ?? null ),
                etymology: $this->normalizeNullable( $data['etymology'] ?? null ),
                parentId: $this->normalizeNullableId( $data['parent_id'] ?? null ),
                evolutionProfile: $entry?->evolutionProfile,
                notes: $this->normalizeNullable( $data['notes'] ?? null ),
                sourcePageId: $entry?->sourcePageId,
                createdAt: $entry?->createdAt,
                updatedAt: $entry?->updatedAt
            );

            if ( $entry ) {
                $lexiconStore->update( $entry->id, $record );
            } else {
                $lexiconStore->insert( $record );
            }

            return true;
        } );

        return $form;
    }

    private function buildEntriesTable( array $entries ): string {
        if ( !$entries ) {
            return $this->buildMessageBox(
                'mw-message-box-notice',
                $this->msg( 'continuumlexiconedit-noentries' )->text()
            );
        }

        $rows = '';
        foreach ( $entries as $entry ) {
            $editLink = $this->getLinkRenderer()->makeLink(
                $this->getPageTitle(),
                $this->msg( 'edit' )->text(),
                [],
                [ 'action' => 'edit', 'id' => $entry->id ]
            );

            $rows .= MWHtml::rawElement(
                'tr',
                [],
                MWHtml::element( 'td', [], $entry->language ) .
                MWHtml::element( 'td', [], $entry->lemma ) .
                MWHtml::element( 'td', [], $entry->romanized ?? '' ) .
                MWHtml::element( 'td', [], $entry->pos ?? '' ) .
                MWHtml::element( 'td', [], $entry->gloss ?? '' ) .
                MWHtml::rawElement(
                    'td',
                    [],
                    $editLink . ' ' . $this->buildDeleteForm( 'delete-entry', $entry->id )
                )
            );
        }

        $header = MWHtml::rawElement(
            'tr',
            [],
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-language' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-lemma' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-romanized' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-pos' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-gloss' )->text() ) .
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

    private function handleDeleteRequest( LexiconStore $lexiconStore ): bool {
        $request = $this->getRequest();
        if ( !$request->wasPosted() || $request->getVal( 'continuum_action' ) !== 'delete-entry' ) {
            return false;
        }

        if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-error',
                $this->msg( 'sessionfailure' )->text()
            ) );
            return false;
        }

        $entryId = max( 0, $request->getInt( 'id', 0 ) );
        if ( $entryId < 1 || !$lexiconStore->getById( $entryId ) ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-warning',
                $this->msg( 'continuumlexiconedit-missing' )->text()
            ) );
            return false;
        }

        $lexiconStore->delete( $entryId );
        $this->getOutput()->redirect( $this->getPageTitle()->getLocalURL( [ 'notice' => 'deleted' ] ) );
        return true;
    }

    private function addNoticeFromRequest(): void {
        $notice = $this->getRequest()->getVal( 'notice', '' );
        if ( $notice === '' ) {
            return;
        }

        $messageKey = match ( $notice ) {
            'created' => 'continuumlexiconedit-created',
            'updated' => 'continuumlexiconedit-updated',
            'deleted' => 'continuumlexiconedit-deleted',
            default => null,
        };

        if ( $messageKey !== null ) {
            $this->getOutput()->addHTML( $this->buildMessageBox(
                'mw-message-box-success',
                $this->msg( $messageKey )->text()
            ) );
        }
    }

    private function buildSelect( string $name, string $id, array $options, string $selected ): string {
        $optionHtml = '';
        foreach ( $options as $label => $value ) {
            $attrs = [ 'value' => (string)$value ];
            if ( (string)$value === $selected ) {
                $attrs['selected'] = 'selected';
            }
            $optionHtml .= MWHtml::element( 'option', $attrs, (string)$label );
        }

        return MWHtml::rawElement( 'select', [ 'name' => $name, 'id' => $id ], $optionHtml );
    }

    private function normalizeNullable( mixed $value ): ?string {
        $value = trim( (string)$value );
        return $value !== '' ? $value : null;
    }

    private function normalizeNullableId( mixed $value ): ?int {
        $value = trim( (string)$value );
        return $value !== '' ? (int)$value : null;
    }

    private function buildMessageBox( string $class, string $message ): string {
        return MWHtml::element( 'p', [ 'class' => "mw-message-box $class" ], $message );
    }
}
