.SUFFIXES = .mo .po
INSTALLDIR = .
LANGUAGES = en_US fr_FR@euro es_ES@euro
PACKAGE = galette
PHP_SOURCES = ../index.php 

all : messages.po
	@for l in ${LANGUAGES}; \
	  do \
	  echo -n "mise à jour de $$l.po"; msgmerge -U $$l.po messages.po; \
	  mkdir -p ${INSTALLDIR}/$$l/LC_MESSAGES; \
	  echo "formattage de ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo."; \
	  msgfmt $$l.po -o ${INSTALLDIR}/$$l/LC_MESSAGES/${PACKAGE}.mo; \
	done

messages.po : ${PHP_SOURCES}
	xgettext -n ${PHP_SOURCES}

