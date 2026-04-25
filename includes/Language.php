<?php

declare( strict_types = 1 );

namespace ContinuumUniverses\Lexicon;

class Language {
    public function __construct(
        public readonly ?int $id,
        public readonly string $key,
        public readonly string $displayName,
        public readonly ?int $parentId = null,
        public readonly ?string $stage = null,
        public readonly ?string $notes = null,
        public readonly ?string $parentDisplayName = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    public static function fromRow( object $row ): self {
        return new self(
            id: (int)$row->cl_id,
            key: (string)$row->cl_key,
            displayName: (string)$row->cl_display_name,
            parentId: $row->cl_parent_id !== null ? (int)$row->cl_parent_id : null,
            stage: $row->cl_stage !== null ? (string)$row->cl_stage : null,
            notes: $row->cl_notes !== null ? (string)$row->cl_notes : null,
            parentDisplayName: property_exists( $row, 'parent_display_name' ) &&
                $row->parent_display_name !== null ? (string)$row->parent_display_name : null,
            createdAt: $row->cl_created_at !== null ? (string)$row->cl_created_at : null,
            updatedAt: $row->cl_updated_at !== null ? (string)$row->cl_updated_at : null
        );
    }

    public function toInsertRow( string $timestamp ): array {
        return [
            'cl_key' => $this->key,
            'cl_display_name' => $this->displayName,
            'cl_parent_id' => $this->parentId,
            'cl_stage' => $this->stage,
            'cl_notes' => $this->notes,
            'cl_created_at' => $timestamp,
            'cl_updated_at' => $timestamp,
        ];
    }

    public function toUpdateRow( string $timestamp ): array {
        return [
            'cl_key' => $this->key,
            'cl_display_name' => $this->displayName,
            'cl_parent_id' => $this->parentId,
            'cl_stage' => $this->stage,
            'cl_notes' => $this->notes,
            'cl_updated_at' => $timestamp,
        ];
    }
}
