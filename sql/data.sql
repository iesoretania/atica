SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


INSERT INTO `configuration` (`id`, `item_id`, `organization_id`, `content_type`, `content_subtype`, `order_nr`, `description`, `content`, `is_organization_preference`, `is_snapshot_preference`) VALUES
(1, 'guest.welcome', NULL, 10, NULL, 0, 'Texto de bienvenida de los invitados', '<p>Desde aquí podrá acceder a información sobre el centro y los criterios de evaluación de las asignaturas. Haga clic con el ratón en la sección de la barra lateral que desee consultar.</p>\n\n<p>Para cualquier cuestión sobre los datos de la aplicación, estaremos encantados de atenderles en el propio centro, por teléfono o por correo electrónico.</p>\n\n<p><i>Le saluda atentamente la Comisión de Calidad.</i></p>', 1, 0),
(2, 'login', NULL, NULL, NULL, 0, 'Entrada a la aplicación', 'Ajustes relacionados con la pantalla de entrada a la aplicación', 0, 0),
(3, 'login.help', NULL, 0, NULL, 300, 'Ayuda en caso de problemas', 'Contacte con el coordinador de calidad si tiene problemas de acceso', 1, 0),
(4, 'login.hint.password', NULL, 0, NULL, 200, 'Ayuda de contraseña', 'Contraseña de ÁTICA', 1, 0),
(6, 'login.hint.username', NULL, 0, NULL, 100, 'Ayuda de nombre de usuario', 'El mismo utilizado en la aplicación Séneca', 1, 0),
(7, 'calendar.base_week', NULL, 3, NULL, 100, 'Semana inicial del curso', '33', 1, 1),
(8, 'user.welcome', NULL, 10, NULL, 100, 'Bienvenida de los usuarios', '<p>Desde aquí podrá acceder a información sobre el Sistema de Gestión de la Calidad del centro. Haga clic con el ratón en la sección de la barra lateral que desee consultar.</p>\r\n\r\n<p>Para cualquier cuestión sobre los datos de la aplicación busque a un miembro de la Comisión de Calidad, le atenderemos gustosamente.</p>\r\n\r\n<p><i>Le saluda atentamente la Comisión de Calidad.</i></p>', 1, 0);

INSERT INTO `module` (`id`, `name`, `version`, `cron`, `last_cron`, `is_active`) VALUES
(1, 'core', '2014050501', 0, 0, 1);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
