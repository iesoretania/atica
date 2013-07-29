-- phpMyAdmin SQL Dump
-- version 3.5.7
-- http://www.phpmyadmin.net
--
-- Servidor: localhost
-- Tiempo de generación: 29-07-2013 a las 14:11:38
-- Versión del servidor: 5.5.29
-- Versión de PHP: 5.4.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de datos: 'atica'
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'action'
--

CREATE TABLE "action" (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'action_type'
--

CREATE TABLE action_type (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'activity'
--

CREATE TABLE activity (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id),
  KEY activity_organization_id_fk (organization_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'activity_event'
--

CREATE TABLE activity_event (
  activity_id int(11) unsigned NOT NULL,
  event_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  PRIMARY KEY (activity_id,event_id),
  KEY activity_event_event_id_fk (event_id),
  KEY activity_event_activity_id_fk (activity_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'activity_profile'
--

CREATE TABLE activity_profile (
  activity_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (activity_id,profile_id),
  KEY activity_profile_event_id_fk (activity_id),
  KEY activity_profile_profile_id_fk (profile_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'category'
--

CREATE TABLE category (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'completed_event'
--

CREATE TABLE completed_event (
  event_id int(11) unsigned NOT NULL,
  person_id int(11) unsigned NOT NULL,
  completed_date date NOT NULL,
  PRIMARY KEY (event_id,person_id),
  KEY completed_event_event_id_fk (event_id),
  KEY completed_event_person_id_fk (person_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'configuration'
--

CREATE TABLE configuration (
  id varchar(255) NOT NULL,
  organization_id int(11) unsigned DEFAULT NULL,
  content_type int(11) unsigned DEFAULT NULL,
  content_subtype int(11) unsigned DEFAULT NULL,
  order_nr int(11) unsigned NOT NULL,
  description varchar(255) NOT NULL,
  content text,
  is_organization_preference tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_snapshot_preference tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY configuration_organization_id_fk (organization_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'delivery'
--

CREATE TABLE delivery (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_id int(11) unsigned DEFAULT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  creation_date datetime NOT NULL,
  current_revision_id int(11) unsigned DEFAULT NULL,
  public_token varchar(45) DEFAULT NULL,
  item_id int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  KEY delivery_current_revision_id_fk (current_revision_id),
  KEY delivery_profile_id_fk (profile_id),
  KEY delivery_item_id_fk (item_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'document'
--

CREATE TABLE document (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'document_data'
--

CREATE TABLE document_data (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  download_path varchar(255) DEFAULT NULL,
  download_filesize int(11) unsigned DEFAULT NULL,
  data_hash char(40) DEFAULT NULL,
  binary_data longblob,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'event'
--

CREATE TABLE "event" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  from_week int(11) unsigned NOT NULL,
  to_week int(11) unsigned NOT NULL,
  is_automatic tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_manual tinyint(1) unsigned NOT NULL DEFAULT '1',
  is_visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY event_organization_id_fk (organization_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'event_delivery'
--

CREATE TABLE event_delivery (
  event_id int(11) unsigned NOT NULL,
  delivery_id int(11) unsigned NOT NULL,
  description text,
  PRIMARY KEY (event_id,delivery_id),
  KEY event_delivery_event_id_fk (event_id),
  KEY event_delivery_delivery_id_fk (delivery_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'event_folder'
--

CREATE TABLE event_folder (
  event_id int(11) unsigned NOT NULL,
  folder_id int(11) unsigned NOT NULL,
  description text,
  is_mandatory tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (event_id,folder_id),
  KEY event_folder_event_id_fk (event_id),
  KEY event_folder_folder_id_fk (folder_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'event_profile'
--

CREATE TABLE event_profile (
  event_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (event_id,profile_id),
  KEY event_profile_event_id (event_id),
  KEY event_profile_profile_id (profile_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'file_extension'
--

CREATE TABLE file_extension (
  id varchar(45) NOT NULL,
  mime varchar(255) NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  icon varchar(255) NOT NULL,
  convertible tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'folder'
--

CREATE TABLE folder (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  category_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  description text,
  is_divided tinyint(1) unsigned NOT NULL DEFAULT '0',
  is_visible tinyint(1) unsigned NOT NULL DEFAULT '1',
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'folder_delivery'
--

CREATE TABLE folder_delivery (
  folder_id int(11) unsigned NOT NULL,
  delivery_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  snapshot_id int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (folder_id,delivery_id),
  KEY folder_delivery_folder_id_fk (folder_id),
  KEY folder_delivery_delivery_id_fk (delivery_id),
  KEY folder_delivery_snapshot_id_fk_idx (snapshot_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'folder_permission'
--

CREATE TABLE folder_permission (
  folder_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  permission tinyint(4) NOT NULL,
  PRIMARY KEY (folder_id,profile_id,permission),
  KEY folder_permission_folder_id (folder_id),
  KEY folder_permission_group_id (profile_id),
  KEY folder_permission_permission (permission)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'folder_profile_delivery_item'
--

CREATE TABLE folder_profile_delivery_item (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_id int(11) unsigned NOT NULL,
  folder_id int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY folder_profile_delivery_item_profile_id_fk (profile_id),
  KEY folder_profile_delivery_item_folder_id_fk (folder_id),
  KEY folder_profile_delivery_item_profile_id_fk_idx (id),
  KEY folder_profile_delivery_item_folder_id_fk_idx (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'grouping'
--

CREATE TABLE grouping (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'grouping_folder'
--

CREATE TABLE grouping_folder (
  grouping_id int(11) unsigned NOT NULL,
  folder_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL DEFAULT '0',
  alt_display_name varchar(255) DEFAULT NULL,
  PRIMARY KEY (grouping_id,folder_id),
  KEY activity_folder_activity_id_fk (grouping_id),
  KEY activity_folder_folder_id_fk (folder_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'grouping_profile'
--

CREATE TABLE grouping_profile (
  grouping_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (grouping_id,profile_id),
  KEY grouping_profile_group_grouping_id (grouping_id),
  KEY grouping_profile_group_profile_group_id (profile_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'non_conformance'
--

CREATE TABLE non_conformance (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'non_conformance_type'
--

CREATE TABLE non_conformance_type (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(255) NOT NULL,
  description text,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'organization'
--

CREATE TABLE organization (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  display_name varchar(45) NOT NULL,
  "code" varchar(45) NOT NULL,
  url_prefix varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'person'
--

CREATE TABLE person (
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
  blocked_access datetime DEFAULT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'person_organization'
--

CREATE TABLE person_organization (
  person_id int(11) unsigned NOT NULL,
  organization_id int(11) unsigned NOT NULL,
  is_active tinyint(1) unsigned NOT NULL DEFAULT '1',
  is_local_administrator tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (person_id,organization_id),
  KEY person_organization_person_id_fk (person_id),
  KEY person_organization_organization_id_fk (organization_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'person_preferences'
--

CREATE TABLE person_preferences (
  person_id int(11) unsigned NOT NULL,
  preference varchar(255) NOT NULL,
  "value" varchar(255) DEFAULT NULL,
  PRIMARY KEY (person_id,preference),
  KEY person_preferences_person_id_fk (person_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'person_profile'
--

CREATE TABLE person_profile (
  person_id int(11) unsigned NOT NULL,
  profile_id int(11) unsigned NOT NULL,
  PRIMARY KEY (person_id,profile_id),
  KEY person_profile_person_fk (person_id),
  KEY person_profile_profile_fk (profile_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'profile'
--

CREATE TABLE "profile" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  profile_group_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) DEFAULT '',
  description text,
  PRIMARY KEY (id),
  KEY profile_profile_group_id_fk (profile_group_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'profile_group'
--

CREATE TABLE profile_group (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  display_name_male varchar(255) NOT NULL,
  display_name_female varchar(255) NOT NULL,
  display_name_neutral varchar(255) NOT NULL,
  abbreviation varchar(5) DEFAULT NULL,
  is_manager tinyint(1) unsigned NOT NULL DEFAULT '0',
  description text,
  PRIMARY KEY (id),
  KEY profile_group_organization_id_fk_idx (organization_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'revision'
--

CREATE TABLE revision (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  delivery_id int(11) unsigned NOT NULL,
  revision_nr int(11) unsigned NOT NULL,
  uploader_person_id int(11) unsigned NOT NULL,
  "status" int(11) unsigned DEFAULT NULL,
  upload_date datetime NOT NULL,
  upload_comment text,
  original_document int(11) unsigned DEFAULT NULL,
  autogenerated_date datetime DEFAULT NULL,
  template int(11) unsigned DEFAULT NULL,
  template_instance int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  KEY revision_delivery_id_fk (delivery_id),
  KEY revision_uploader_person_id_fk (uploader_person_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'revision_comment'
--

CREATE TABLE revision_comment (
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'session'
--

CREATE TABLE "session" (
  id varchar(64) NOT NULL,
  person_id int(11) unsigned NOT NULL,
  expiration datetime NOT NULL,
  PRIMARY KEY (id,person_id),
  KEY session_person_fk (person_id)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla 'snapshot'
--

CREATE TABLE "snapshot" (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  organization_id int(11) unsigned NOT NULL,
  order_nr int(11) unsigned NOT NULL,
  display_name varchar(255) NOT NULL,
  visible tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (id),
  KEY snapshot_organization_id_fk (organization_id)
);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `action`
--
ALTER TABLE `action`
  ADD CONSTRAINT action_action_type_id FOREIGN KEY (action_type_id) REFERENCES action_type (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT action_non_conformance_id_fk FOREIGN KEY (non_conformance_id) REFERENCES non_conformance (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT action_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT activity_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id);

--
-- Filtros para la tabla `activity_event`
--
ALTER TABLE `activity_event`
  ADD CONSTRAINT activity_event_activity_id_fk FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_event_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `activity_profile`
--
ALTER TABLE `activity_profile`
  ADD CONSTRAINT activity_profile_group_activity_id_fk FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_profile_group_event_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `category`
--
ALTER TABLE `category`
  ADD CONSTRAINT category_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `completed_event`
--
ALTER TABLE `completed_event`
  ADD CONSTRAINT completed_event_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE,
  ADD CONSTRAINT completed_event_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON UPDATE CASCADE;

--
-- Filtros para la tabla `configuration`
--
ALTER TABLE `configuration`
  ADD CONSTRAINT configuration_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT delivery_current_revision_id_fk FOREIGN KEY (current_revision_id) REFERENCES revision (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT delivery_item_id_fk FOREIGN KEY (item_id) REFERENCES folder_profile_delivery_item (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT delivery_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT document_document_data_fk FOREIGN KEY (document_data_id) REFERENCES document_data (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT document_extension_fk FOREIGN KEY (extension_id) REFERENCES file_extension (id),
  ADD CONSTRAINT document_revision_id_fk FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE CASCADE;

--
-- Filtros para la tabla `event`
--
ALTER TABLE `event`
  ADD CONSTRAINT event_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id);

--
-- Filtros para la tabla `event_delivery`
--
ALTER TABLE `event_delivery`
  ADD CONSTRAINT event_delivery_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT event_delivery_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `event_folder`
--
ALTER TABLE `event_folder`
  ADD CONSTRAINT event_folder_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE,
  ADD CONSTRAINT event_folder_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON UPDATE CASCADE;

--
-- Filtros para la tabla `event_profile`
--
ALTER TABLE `event_profile`
  ADD CONSTRAINT event_profile_group_event_id_fk FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT event_profile_group_profile_group_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `folder`
--
ALTER TABLE `folder`
  ADD CONSTRAINT folder_category_id_fk FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `folder_delivery`
--
ALTER TABLE `folder_delivery`
  ADD CONSTRAINT folder_delivery_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE,
  ADD CONSTRAINT folder_delivery_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE,
  ADD CONSTRAINT folder_delivery_snapshot_id_fk FOREIGN KEY (snapshot_id) REFERENCES snapshot (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `folder_permission`
--
ALTER TABLE `folder_permission`
  ADD CONSTRAINT folder_permission_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT folder_permission_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE;

--
-- Filtros para la tabla `folder_profile_delivery_item`
--
ALTER TABLE `folder_profile_delivery_item`
  ADD CONSTRAINT folder_profile_delivery_item_folder_id_fk FOREIGN KEY (id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT folder_profile_delivery_item_profile_id_fk FOREIGN KEY (id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `grouping`
--
ALTER TABLE `grouping`
  ADD CONSTRAINT grouping_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `grouping_folder`
--
ALTER TABLE `grouping_folder`
  ADD CONSTRAINT activity_folder_folder_id_fk FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT activity_folder_grouping_id_fk FOREIGN KEY (grouping_id) REFERENCES grouping (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `grouping_profile`
--
ALTER TABLE `grouping_profile`
  ADD CONSTRAINT grouping_profile_grouping_id_fk FOREIGN KEY (grouping_id) REFERENCES grouping (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT grouping_profile_profile_id_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `non_conformance`
--
ALTER TABLE `non_conformance`
  ADD CONSTRAINT non_conformance_closed_by_fk FOREIGN KEY (closed_by) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_opened_by_fk FOREIGN KEY (opened_by) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT non_conformance_type_fk FOREIGN KEY (non_conformance_type_id) REFERENCES non_conformance_type (id);

--
-- Filtros para la tabla `person_organization`
--
ALTER TABLE `person_organization`
  ADD CONSTRAINT person_organization_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT person_organization_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `person_preferences`
--
ALTER TABLE `person_preferences`
  ADD CONSTRAINT person_preferences_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id);

--
-- Filtros para la tabla `person_profile`
--
ALTER TABLE `person_profile`
  ADD CONSTRAINT person_profile_person_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT person_profile_profile_fk FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE;

--
-- Filtros para la tabla `profile`
--
ALTER TABLE `profile`
  ADD CONSTRAINT profile_profile_group_id_fk FOREIGN KEY (profile_group_id) REFERENCES profile_group (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `profile_group`
--
ALTER TABLE `profile_group`
  ADD CONSTRAINT profile_group_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `revision`
--
ALTER TABLE `revision`
  ADD CONSTRAINT revision_delivery_id_fk FOREIGN KEY (delivery_id) REFERENCES delivery (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT revision_uploader_person_id_fk FOREIGN KEY (uploader_person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `revision_comment`
--
ALTER TABLE `revision_comment`
  ADD CONSTRAINT revision_comment_person_id_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT revision_comment_revision_id_fk FOREIGN KEY (revision_id) REFERENCES revision (id) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `session`
--
ALTER TABLE `session`
  ADD CONSTRAINT session_person_fk FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `snapshot`
--
ALTER TABLE `snapshot`
  ADD CONSTRAINT snapshot_organization_id_fk FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
