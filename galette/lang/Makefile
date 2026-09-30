INSTALLDIR = .
LANGUAGES = `find ./ -name "galette_*.po" -print | sed 's|^\./galette_\(.*\).po|\1|'`
DOMAINS = galette
PHP_SOURCES = $(shell find ../ -maxdepth 1 -name \*.php) \
              $(shell find ../lib/Galette -name \*.php) \
              $(shell find ../webroot -name \*.php) \
              $(shell find ../includes -name \*.php) \
              $(shell find ../install -name \*.php) \
              $(shell find ../../tempcache -name \*.twig)


all : extract mo

twig :
	pushd ../../ && bin/console galette:twig-cache

po :
	@echo "Generating PO files:"
	@for l in ${LANGUAGES}; do \
	  for d in ${DOMAINS}; do \
	    if [ -f $${d}_$${l}.po ]; then \
	      echo -n "  Updating $${d}_$${l}.po"; \
	      msgmerge -U $${d}_$${l}.po $$d.pot >/dev/null ; \
	    else \
	      echo "  Creating of $${d}_$${l}.po"; \
	      msginit -o $${d}_$${l}.po -i $$d.pot >/dev/null ; \
	    fi; \
	  done \
	done

mo :
	cd ../../ && bin/console galette:compile-locales

extract : twig ${PHP_SOURCES} Makefile
	xgettext ${PHP_SOURCES} --keyword=_T --keyword=__ --keyword=_Tn:1,2 --keyword=_Tx:1c,2 --keyword=_Tnx:1c,2,3  -L PHP --from-code=UTF-8 --add-comments=TRANS --msgid-bugs-address=translations@galette.eu --force-po -o galette.pot;
	cd ../../ && bin/console galette:twig-pot-references galette/lang/galette.pot;
	echo "Generating en_US:"
	@for d in ${DOMAINS}; do \
	  LANG=C msginit --no-translator -i $$d.pot --locale=en_US.utf8 -o $${d}_en_US.po; \
	done
