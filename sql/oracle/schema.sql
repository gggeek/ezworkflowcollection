
CREATE TABLE ezxapprovelocation_items (
  collaboration_id INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  workflow_process_id INTEGER DEFAULT 0 NOT NULL,
  target_node_ids VARCHAR2(255) default '' NOT NULL, -- !!
  PRIMARY KEY  ( id )
);

CREATE SEQUENCE s_approvelocation_items;

CREATE TRIGGER ezxapprovelocation_items_id_tr
BEFORE INSERT ON ezxapprovelocation_items FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
    SELECT s_approvelocation_items.nextval INTO :new.id FROM dual;
END;
/