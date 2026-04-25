<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

class LexiconEntry {
    public function __construct(
        public readonly ?int $id,
        public readonly string $language,
        public readonly string $lemma,
        public readonly ?int $languageId = null,
        public readonly ?string $romanized = null,
        public readonly ?string $ipa = null,
        public readonly ?string $pos = null,
        public readonly ?string $gloss = null,
        public readonly ?string $definition = null,
        public readonly ?string $etymology = null,
        public readonly ?int $parentId = null,
        public readonly ?string $evolutionProfile = null,
        public readonly ?string $notes = null,
        public readonly ?int $sourcePageId = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public static function fromRow( object $row ): self {
        return new self(
            id: (int)$row->clx_id,
            language: property_exists( $row, 'language_display_name' ) &&
                $row->language_display_name !== null ? (string)$row->language_display_name : (string)$row->clx_language,
            languageId: $row->clx_language_id !== null ? (int)$row->clx_language_id : null,
            lemma: (string)$row->clx_lemma,
            romanized: $row->clx_romanized !== null ? (string)$row->clx_romanized : null,
            ipa: $row->clx_ipa !== null ? (string)$row->clx_ipa : null,
            pos: $row->clx_pos !== null ? (string)$row->clx_pos : null,
            gloss: $row->clx_gloss !== null ? (string)$row->clx_gloss : null,
            definition: $row->clx_definition !== null ? (string)$row->clx_definition : null,
            etymology: $row->clx_etymology !== null ? (string)$row->clx_etymology : null,
            parentId: $row->clx_parent_id !== null ? (int)$row->clx_parent_id : null,
            evolutionProfile: $row->clx_evolution_profile !== null ? (string)$row->clx_evolution_profile : null,
            notes: $row->clx_notes !== null ? (string)$row->clx_notes : null,
            sourcePageId: $row->clx_source_page_id !== null ? (int)$row->clx_source_page_id : null,
            createdAt: $row->clx_created_at !== null ? (string)$row->clx_created_at : null,
            updatedAt: $row->clx_updated_at !== null ? (string)$row->clx_updated_at : null
        );
    }

    public function toInsertRow( string $timestamp ): array {
        return [
            'clx_language' => $this->language,
            'clx_language_id' => $this->languageId,
            'clx_lemma' => $this->lemma,
            'clx_romanized' => $this->romanized,
            'clx_ipa' => $this->ipa,
            'clx_pos' => $this->pos,
            'clx_gloss' => $this->gloss,
            'clx_definition' => $this->definition,
            'clx_etymology' => $this->etymology,
            'clx_parent_id' => $this->parentId,
            'clx_evolution_profile' => $this->evolutionProfile,
            'clx_notes' => $this->notes,
            'clx_source_page_id' => $this->sourcePageId,
            'clx_created_at' => $timestamp,
            'clx_updated_at' => $timestamp
        ];
    }

    public function toUpdateRow( string $timestamp ): array {
        return [
            'clx_language' => $this->language,
            'clx_language_id' => $this->languageId,
            'clx_lemma' => $this->lemma,
            'clx_romanized' => $this->romanized,
            'clx_ipa' => $this->ipa,
            'clx_pos' => $this->pos,
            'clx_gloss' => $this->gloss,
            'clx_definition' => $this->definition,
            'clx_etymology' => $this->etymology,
            'clx_parent_id' => $this->parentId,
            'clx_evolution_profile' => $this->evolutionProfile,
            'clx_notes' => $this->notes,
            'clx_source_page_id' => $this->sourcePageId,
            'clx_updated_at' => $timestamp
        ];
    }
}
