
CREATE SEQUENCE approvelocation_items_s
    START 1
    INCREMENT 1
    MAXVALUE 9223372036854775807
    MINVALUE 1
    CACHE 1;

CREATE TABLE ezxapprovelocation_items (
  collaboration_id INTEGER NOT NULL DEFAULT 0,
  id INTEGER NOT NULL DEFAULT nextval('approvelocation_items_s'::text),
  workflow_process_id INTEGER NOT NULL DEFAULT 0,
  target_node_ids VARCHAR(255) NOT NULL default '', -- !!
  PRIMARY KEY  ( id )
);
