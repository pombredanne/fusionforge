
YUICOMPRESSOR = java -jar plugins/wiki/www/themes/default/yuicompressor-2.4.7.jar

all: gettext js

gettext:
	utils/manage-translations.sh check
	utils/manage-translations.sh refresh
	utils/manage-translations.sh build

js: www/js/common-min.js www/js/jquery-common-min.js

www/js/common-min.js: www/js/common.js www/js/sortable.js
	cat $^ > /tmp/combined.js
	$(YUICOMPRESSOR) -o $@ /tmp/combined.js
	rm -f /tmp/combined.js

www/js/jquery-common-min.js: vendor/jquery/jquery-1.7.2.min.js vendor/jquery-tipsy/src/javascripts/jquery.tipsy.js vendor/coolfieldset/js/jquery.coolfieldset.js www/js/jquery-common.js
	cat $^ > /tmp/combined.js
	$(YUICOMPRESSOR) -o $@ /tmp/combined.js
	rm -f /tmp/combined.js
