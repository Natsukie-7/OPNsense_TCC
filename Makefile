PLUGIN_NAME=		OPNSense_User_Register
PLUGIN_VERSION=		2.0.0
PLUGIN_COMMENT=		Importacao de alunos XLSX com previa e contas locais
PLUGIN_MAINTAINER=	opnsensedev@gmail.com
PLUGIN_DEVEL=		yes
PLUGIN_DEPENDS=		php${PLUGIN_PHP}-zip

.include "../../Mk/plugins.mk"