CREATE TABLE /*_*/continuum_languages (
  cl_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cl_key          VARBINARY(64) NOT NULL,
  cl_display_name VARBINARY(255) NOT NULL,
  cl_parent_id    INT UNSIGNED DEFAULT NULL,
  cl_stage        VARBINARY(64) DEFAULT NULL,
  cl_notes        MEDIUMBLOB DEFAULT NULL,
  cl_created_at   BINARY(14) NOT NULL,
  cl_updated_at   BINARY(14) NOT NULL,
  PRIMARY KEY (cl_id),
  UNIQUE KEY cl_key (cl_key),
  KEY cl_display_name (cl_display_name),
  KEY cl_parent_id (cl_parent_id)
) /*$wgDBTableOptions*/;
