CREATE TABLE IF NOT EXISTS "action" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  action_type_id int(11) unsigned NOT NULL,
  non_conformance_id int(11) unsigned DEFAULT NULL,
  action_left int(11) unsigned DEFAULT NULL,
  action_right int(11) unsigned DEFAULT NULL,
  action_level int(11) unsigned DEFAULT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  check_due date NOT NULL,
  checked_at date DEFAULT NULL,
  result int(11) unsigned DEFAULT NULL,
  result_description text,
  PRIMARY KEY (id),
  KEY action_organization_id_fk (organization_id),
  KEY action_non_conformance_id_fk (non_conformance_id),
  KEY action_action_type_id (action_type_id)
);

CREATE TABLE IF NOT EXISTS action_type (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS activity (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id),
  KEY activity_organization_id_fk (organization_id)
);

CREATE TABLE IF NOT EXISTS activity_event (
  activity_id int(11) unsigned NOT NULL,
  event_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  PRIMARY KEY (activity_id,event_id),
  KEY activity_event_event_id_fk (event_id),
  KEY activity_event_activity_id_fk (activity_id)
);

CREATE TABLE IF NOT EXISTS activity_profile (
  activity_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (activity_id,profile_id),
  KEY activity_profile_event_id_fk (activity_id),
  KEY activity_profile_profile_id_fk (profile_id)
);

CREATE TABLE IF NOT EXISTS category (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  category_left int(11) unsigned DEFAULT NULL,
  category_right int(11) unsigned DEFAULT NULL,
  category_level int(11) unsigned DEFAULT NULL,
  "code" varchar(45) DEFAULT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id),
  KEY category_organization_id_fk (organization_id)
);

CREATE TABLE IF NOT EXISTS completed_event (
  event_id int(11) unsigned NOT NULL,
  person_id int(11) unsigned NOT NULL,
  completed_date date NOT NULL,
  PRIMARY KEY (event_id,person_id),
  KEY completed_event_event_id_fk (event_id),
  KEY completed_event_person_id_fk (person_id)
);

CREATE TABLE IF NOT EXISTS configuration (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  item_id varchar(255) NOT NULL,
  organization_id int(11) unsigned DEFAULT NULL,
  content_type int(11) unsigned DEFAULT NULL,
  content_subtype int(11) unsigned DEFAULT NULL,
  order_nr int(11) unsigned NOT NULL,
  description varchar(255) NOT NULL,
  content text,
  is_organization_preference tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_snapshot_preference tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  UNIQUE KEY configuration_item_id_organization_id_uk (item_id,organization_id),
  KEY configuration_organization_id_fk (organization_id)
);

CREATE TABLE IF NOT EXISTS delivery (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_id int(11) unsigned DEFAULT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  creation_date datetime NOT NULL,
  current_revision_id int(11) unsigned DEFAULT NULL,
  is_visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  public_token varchar(45) DEFAULT NULL,
  item_id int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  KEY delivery_current_revision_id_fk (current_revision_id),
  KEY delivery_profile_id_fk (profile_id),
  KEY delivery_item_id_fk (item_id)
);

CREATE TABLE IF NOT EXISTS document (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  revision_id int(11) unsigned NOT NULL,
  download_filename varchar(255) NOT NULL,
  extension_id varchar(45) DEFAULT NULL,
  document_data_id int(11) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY document_revision_id_fk (revision_id),
  KEY document_extension_fk (extension_id),
  KEY document_document_data_fk (document_data_id)
);

CREATE TABLE IF NOT EXISTS document_data (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  download_path varchar(255) DEFAULT NULL,
  download_filesize int(11) unsigned DEFAULT NULL,
  data_hash char(40) DEFAULT NULL,
  binary_data longblob,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS "event" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  folder_id int(11) unsigned DEFAULT NULL,
  from_week int(11) unsigned DEFAULT NULL,
  to_week int(11) unsigned DEFAULT NULL,
  period_description varchar(255) DEFAULT NULL,
  is_automatic tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_manual tinyint(1) unsigned NOT NULL DEFAULT '1',
  is_visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY event_organization_id_fk (organization_id),
  KEY event_folder_id_fk (folder_id)
);

CREATE TABLE IF NOT EXISTS event_delivery (
  event_id int(11) unsigned NOT NULL,
  delivery_id int(11) unsigned NOT NULL,
  description text,
  PRIMARY KEY (event_id,delivery_id),
  KEY event_delivery_event_id_fk (event_id),
  KEY event_delivery_delivery_id_fk (delivery_id)
);

CREATE TABLE IF NOT EXISTS event_profile (
  event_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (event_id,profile_id),
  KEY event_profile_event_id (event_id),
  KEY event_profile_profile_id (profile_id)
);

CREATE TABLE IF NOT EXISTS file_extension (
  id varchar(45) NOT NULL,
  mime varchar(255) NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  icon varchar(255) NOT NULL,
  convertible tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS folder (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  category_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  is_divided tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  is_restricted tinyint(1) unsigned NOT NULL DEFAULT '0',
  has_snapshot tinyint(1) unsigned NOT NULL DEFAULT '0',
  filter varchar(255) DEFAULT NULL,
  filter_description text,
  mandatory_review tinyint(1) unsigned NOT NULL,
  mandatory_approval tinyint(1) unsigned NOT NULL,
  show_revision_nr tinyint(1) unsigned NOT NULL DEFAULT '0',
  auto_clean tinyint(1) unsigned NOT NULL DEFAULT '0',
  public_token varchar(45) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY folder_category_id_fk_idx (category_id)
);

CREATE TABLE IF NOT EXISTS folder_delivery (
  folder_id int(11) unsigned NOT NULL,
  delivery_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  snapshot_id int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (folder_id,delivery_id),
  KEY folder_delivery_folder_id_fk (folder_id),
  KEY folder_delivery_delivery_id_fk (delivery_id),
  KEY folder_delivery_snapshot_id_fk_idx (snapshot_id)
);

CREATE TABLE IF NOT EXISTS folder_permission (
  folder_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  permission tinyint(4) NOT NULL,
  PRIMARY KEY (folder_id,profile_id,permission),
  KEY folder_permission_folder_id (folder_id),
  KEY folder_permission_group_id (profile_id),
  KEY folder_permission_permission (permission)
);

CREATE TABLE IF NOT EXISTS folder_profile_delivery_item (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_id int(11) unsigned NOT NULL,
  folder_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  is_visible tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY folder_profile_delivery_item_profile_id_fk (profile_id),
  KEY folder_profile_delivery_item_folder_id_fk (folder_id),
  KEY folder_profile_delivery_item_profile_id_fk_idx (id),
  KEY folder_profile_delivery_item_folder_id_fk_idx (id)
);

CREATE TABLE IF NOT EXISTS grouping (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  grouping_left int(11) unsigned NOT NULL,
  grouping_right int(11) unsigned NOT NULL,
  grouping_level int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  "code" varchar(45) DEFAULT NULL,
  guest_access tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY grouping_organization_id_fk (organization_id)
);

CREATE TABLE IF NOT EXISTS grouping_folder (
  grouping_id int(11) unsigned NOT NULL,
  folder_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL DEFAULT '0',
  alt_display_name varchar(255) DEFAULT NULL,
  PRIMARY KEY (grouping_id,folder_id),
  KEY activity_folder_activity_id_fk (grouping_id),
  KEY activity_folder_folder_id_fk (folder_id)
);

CREATE TABLE IF NOT EXISTS grouping_profile (
  grouping_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (grouping_id,profile_id),
  KEY grouping_profile_group_grouping_id (grouping_id),
  KEY grouping_profile_group_profile_group_id (profile_id)
);

CREATE TABLE IF NOT EXISTS log (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  "time" datetime NOT NULL,
  person_id int(11) unsigned DEFAULT NULL,
  ip varchar(45) NOT NULL,
  organization_id int(11) unsigned DEFAULT NULL,
  module varchar(20) NOT NULL,
  command int(11) unsigned NOT NULL,
  "action" varchar(40) NOT NULL,
  url varchar(255) NOT NULL,
  info varchar(255) NOT NULL,
  activity_id int(11) unsigned DEFAULT NULL,
  event_id int(11) unsigned DEFAULT NULL,
  grouping_id int(11) unsigned DEFAULT NULL,
  folder_id int(11) unsigned DEFAULT NULL,
  profile_id int(11) unsigned DEFAULT NULL,
  delivery_id int(11) unsigned DEFAULT NULL,
  revision_id int(11) unsigned DEFAULT NULL,
  document_id int(11) unsigned DEFAULT NULL,
  delivery_item_id int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS non_conformance (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  non_conformance_type_id int(11) unsigned NOT NULL,
  non_conformity_type_detail varchar(255) DEFAULT NULL,
  "code" varchar(45) NOT NULL,
  description text,
  opened_at date NOT NULL,
  opened_by int(11) unsigned NOT NULL,
  closed_at date DEFAULT NULL,
  closed_by int(11) unsigned DEFAULT NULL,
  close_comment text,
  PRIMARY KEY (id),
  KEY non_conformance_organization_id_fk (organization_id),
  KEY non_conformance_opened_by_fk (opened_by),
  KEY non_conformance_closed_by_fk (closed_by),
  KEY non_conformance_type_fk (non_conformance_type_id)
);

CREATE TABLE IF NOT EXISTS non_conformance_type (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS organization (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(65) NOT NULL,
  additional_info varchar(65) DEFAULT NULL,
  "code" varchar(45) NOT NULL,
  url_prefix varchar(255) DEFAULT NULL,
  logo varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS person (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  user_name varchar(50) NOT NULL,
  display_name varchar(255) NOT NULL,
  first_name varchar(45) DEFAULT NULL,
  last_name varchar(90) DEFAULT NULL,
  initials varchar(10) DEFAULT NULL,
  description text,
  "password" varchar(41) DEFAULT NULL,
  gender int(11) unsigned NOT NULL DEFAULT '0',
  email varchar(255) DEFAULT NULL,
  email_enabled tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_global_administrator tinyint(1) unsigned NOT NULL DEFAULT '0',
  token varchar(45) DEFAULT NULL,
  token_expiration datetime DEFAULT NULL,
  token_operation int(11) unsigned DEFAULT NULL,
  token_data varchar(255) DEFAULT NULL,
  last_login datetime DEFAULT NULL,
  retry_count int(11) NOT NULL DEFAULT '0',
  blocked_access datetime DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS person_organization (
  person_id int(11) unsigned NOT NULL,
  organization_id int(11) unsigned NOT NULL,
  is_active tinyint(1) unsigned NOT NULL DEFAULT '1',
  is_local_administrator tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (person_id,organization_id),
  KEY person_organization_person_id_fk (person_id),
  KEY person_organization_organization_id_fk (organization_id)
);

CREATE TABLE IF NOT EXISTS person_preferences (
  person_id int(11) unsigned NOT NULL,
  preference varchar(255) NOT NULL,
  "value" varchar(255) DEFAULT NULL,
  PRIMARY KEY (person_id,preference),
  KEY person_preferences_person_id_fk (person_id)
);

CREATE TABLE IF NOT EXISTS person_profile (
  person_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (person_id,profile_id),
  KEY person_profile_person_fk (person_id),
  KEY person_profile_profile_fk (profile_id)
);

CREATE TABLE IF NOT EXISTS "profile" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_group_id int(11) unsigned DEFAULT NULL,
  is_container tinyint(1) unsigned NOT NULL DEFAULT '0',
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) DEFAULT '',
  description text,
  PRIMARY KEY (id),
  KEY profile_profile_group_id_fk (profile_group_id)
);

CREATE TABLE IF NOT EXISTS profile_group (
  id int(11) unsigned NOT NULL,
  organization_id int(11) unsigned NOT NULL,
  display_name_male varchar(255) NOT NULL,
  display_name_female varchar(255) NOT NULL,
  display_name_neutral varchar(255) NOT NULL,
  abbreviation varchar(5) DEFAULT NULL,
  is_manager tinyint(1) unsigned NOT NULL DEFAULT '0',
  description text,
  PRIMARY KEY (id),
  KEY profile_group_organization_id_fk_idx (organization_id),
  KEY profile_group_id_fk_idx (id)
);

CREATE TABLE IF NOT EXISTS revision (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  delivery_id int(11) unsigned NOT NULL,
  revision_nr int(11) unsigned NOT NULL,
  uploader_person_id int(11) unsigned NOT NULL,
  original_document_id int(11) unsigned DEFAULT NULL,
  "status" int(11) unsigned DEFAULT NULL,
  upload_date datetime NOT NULL,
  upload_comment text,
  autogenerated_date datetime DEFAULT NULL,
  template int(11) unsigned DEFAULT NULL,
  template_instance int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  KEY revision_delivery_id_fk (delivery_id),
  KEY revision_uploader_person_id_fk (uploader_person_id),
  KEY revision_original_document_id (original_document_id)
);

CREATE TABLE IF NOT EXISTS revision_comment (
  person_id int(11) unsigned NOT NULL,
  revision_id int(11) unsigned NOT NULL,
  role int(11) unsigned NOT NULL,
  "date" datetime NOT NULL,
  "comment" text,
  old_status int(11) unsigned DEFAULT NULL,
  new_status int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (person_id,revision_id,role),
  KEY revision_comment_person_id_fk (person_id),
  KEY revision_comment_revision_id_fk (revision_id)
);

CREATE TABLE IF NOT EXISTS "session" (
  id varchar(64) NOT NULL,
  person_id int(11) unsigned NOT NULL,
  expiration datetime NOT NULL,
  PRIMARY KEY (id,person_id),
  KEY session_person_fk (person_id)
);

CREATE TABLE IF NOT EXISTS "snapshot" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY snapshot_organization_id_fk (organization_id)
);


ALTER TABLE `action`
  ADD CONSTRAINT action_action_type_id FOREIGN KEY (action_type_id) REFERENCES action_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT action_non_conformance_id_fk FOREIGN KEY (non_conformance_id) REFERENCES non_conformance (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT action_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `activity`
  ADD CONSTRAINT activity_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id);

ALTER TABLE `activity_event`
  ADD CONSTRAINT activity_event_activity_id_fk FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_event_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `activity_profile`
  ADD CONSTRAINT activity_profile_group_activity_id_fk FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_profile_group_event_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `category`
  ADD CONSTRAINT category_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `completed_event`
  ADD CONSTRAINT completed_event_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE,
  ADD CONSTRAINT completed_event_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON UPDATE CASCADE;

ALTER TABLE `configuration`
  ADD CONSTRAINT configuration_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `delivery`
  ADD CONSTRAINT delivery_current_revision_id_fk FOREIGN KEY (current_revision_id) REFERENCES revision (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT delivery_item_id_fk FOREIGN KEY (item_id) REFERENCES folder_profile_delivery_item (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT delivery_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `document`
  ADD CONSTRAINT document_ibfk_1 FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT document_document_data_fk FOREIGN KEY (document_data_id) REFERENCES document_data (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT document_extension_fk FOREIGN KEY (extension_id) REFERENCES file_extension (id);

ALTER TABLE `event`
  ADD CONSTRAINT event_ibfk_1 FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT event_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id);

ALTER TABLE `event_delivery`
  ADD CONSTRAINT event_delivery_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT event_delivery_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `event_profile`
  ADD CONSTRAINT event_profile_group_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT event_profile_group_profile_group_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `folder`
  ADD CONSTRAINT folder_category_id_fk FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `folder_delivery`
  ADD CONSTRAINT folder_delivery_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE,
  ADD CONSTRAINT folder_delivery_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE,
  ADD CONSTRAINT folder_delivery_snapshot_id_fk FOREIGN KEY (snapshot_id) REFERENCES snapshot (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `folder_permission`
  ADD CONSTRAINT folder_permission_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT folder_permission_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE;

ALTER TABLE `folder_profile_delivery_item`
  ADD CONSTRAINT folder_profile_delivery_item_folder_id_fk FOREIGN KEY (id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT folder_profile_delivery_item_profile_id_fk FOREIGN KEY (id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `grouping`
  ADD CONSTRAINT grouping_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `grouping_folder`
  ADD CONSTRAINT activity_folder_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_folder_grouping_id_fk FOREIGN KEY (grouping_id) REFERENCES grouping (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `grouping_profile`
  ADD CONSTRAINT grouping_profile_grouping_id_fk FOREIGN KEY (grouping_id) REFERENCES grouping (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT grouping_profile_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `non_conformance`
  ADD CONSTRAINT non_conformance_closed_by_fk FOREIGN KEY (closed_by) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_opened_by_fk FOREIGN KEY (opened_by) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_type_fk FOREIGN KEY (non_conformance_type_id) REFERENCES non_conformance_type (id);

ALTER TABLE `person_organization`
  ADD CONSTRAINT person_organization_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT person_organization_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `person_preferences`
  ADD CONSTRAINT person_preferences_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id);

ALTER TABLE `person_profile`
  ADD CONSTRAINT person_profile_person_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT person_profile_profile_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE;

ALTER TABLE `profile`
  ADD CONSTRAINT profile_profile_group_id_fk FOREIGN KEY (profile_group_id) REFERENCES profile_group (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `profile_group`
  ADD CONSTRAINT profile_group_id_fk FOREIGN KEY (id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT profile_group_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `revision`
  ADD CONSTRAINT revision_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT revision_orig_document_id_fk FOREIGN KEY (original_document_id) REFERENCES document (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT revision_uploader_person_id_fk FOREIGN KEY (uploader_person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `revision_comment`
  ADD CONSTRAINT revision_comment_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT revision_comment_revision_id_fk FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `session`
  ADD CONSTRAINT session_person_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `snapshot`
  ADD CONSTRAINT snapshot_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;
