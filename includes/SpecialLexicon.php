<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

use MediaWiki\Html\Html as MWHtml;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialLexicon extends SpecialPage {
    public function __construct() {
        parent::__construct( 'Lexicon', 'continuumlexicon-view' );
    }

    protected function getGroupName(): string {
        return 'poweredbycontinuum';
    }

    public function execute( $subPage ): void {
        $this->setHeaders();
        $out = $this->getOutput();
        $request = $this->getRequest();
        $config = $this->getConfig();

        $language = trim( $request->getVal( 'language', '' ) );
        $pos = trim( $request->getVal( 'pos', '' ) );
        $search = trim( $request->getVal( 'search', '' ) );
        $limit = max( 1, min( 500, $request->getInt( 'limit', (int)$config->get( 'ContinuumLexiconDefaultLimit' ) ) ) );
        $offset = max( 0, $request->getInt( 'offset', 0 ) );

        $store = new LexiconStore();
        $entries = $store->search(
            $language !== '' ? $language : null,
            $pos !== '' ? $pos : null,
            $search !== '' ? $search : null,
            $limit,
            $offset
        );

        $out->addWikiMsg( 'continuumlexicon-summary' );
        $out->addHTML( $this->buildFilterForm( $language, $pos, $search, $limit ) );

        if ( !$entries ) {
            $out->addHTML(
                MWHtml::element( 'p', [ 'class' => 'mw-message-box mw-message-box-notice' ],
                    $this->msg( 'continuumlexicon-noresults' )->text()
                )
            );
            return;
        }

        $out->addHTML( $this->buildResultsTable( $entries ) );
    }

    private function buildFilterForm( string $language, string $pos, string $search, int $limit ): string {
        $title = $this->getPageTitle();

        $html = MWHtml::openElement( 'form', [
            'method' => 'get',
            'action' => $title->getLocalURL()
        ] );

        $html .= MWHtml::hidden( 'title', $title->getPrefixedText() );

        $html .= '<fieldset><legend>' . $this->msg( 'continuumlexicon-filter' )->escaped() . '</legend>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-language' )->text(), 'clx-language' ) . ' ';
        $html .= MWHtml::input( 'language', $language, 'text', [ 'id' => 'clx-language' ] );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-pos' )->text(), 'clx-pos' ) . ' ';
        $html .= MWHtml::input( 'pos', $pos, 'text', [ 'id' => 'clx-pos' ] );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( $this->msg( 'continuumlexicon-search' )->text(), 'clx-search' ) . ' ';
        $html .= MWHtml::input( 'search', $search, 'text', [ 'id' => 'clx-search' ] );
        $html .= '</div>';

        $html .= '<div>';
        $html .= MWHtml::label( 'Limit', 'clx-limit' ) . ' ';
        $html .= MWHtml::input( 'limit', (string)$limit, 'number', [ 'id' => 'clx-limit', 'min' => 1, 'max' => 500 ] );
        $html .= '</div>';

        $html .= MWHtml::submitButton( $this->msg( 'continuumlexicon-filter' )->text() );
        $html .= '</fieldset>';
        $html .= MWHtml::closeElement( 'form' );

        return $html;
    }

    private function buildResultsTable( array $entries ): string {
        $rows = '';

        foreach ( $entries as $entry ) {
            $rows .= MWHtml::openElement( 'tr' );
            $rows .= MWHtml::element( 'td', [], $entry->language );
            $rows .= MWHtml::element( 'td', [], $entry->lemma );
            $rows .= MWHtml::element( 'td', [], $entry->romanized ?? '' );
            $rows .= MWHtml::element( 'td', [], $entry->ipa ?? '' );
            $rows .= MWHtml::element( 'td', [], $entry->pos ?? '' );
            $rows .= MWHtml::element( 'td', [], $entry->gloss ?? '' );
            $rows .= MWHtml::closeElement( 'tr' );
        }

        $header = MWHtml::rawElement( 'tr', [],
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-language' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-lemma' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-romanized' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-ipa' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-pos' )->text() ) .
            MWHtml::element( 'th', [], $this->msg( 'continuumlexicon-gloss' )->text() )
        );

        return MWHtml::rawElement(
            'table',
            [ 'class' => 'wikitable sortable' ],
            $header . $rows
        );
    }
}
