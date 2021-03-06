#! /bin/sh

# Lists which plugins are enabled or disabled.

# Takes into account the 'plugin_status = valid' values if the plugin's etc/pluginname.ini file exists
if ! type confget >/dev/null 2>&1 && ! type python >/dev/null 2>&1 ; then
	echo >&2 Aborting, neither confget nor python are available
	exit 255
fi

if [ -e plugins ] ; then
    cd .
elif [ -e ../src/plugins ] ; then
    cd ../src
else
    echo "Couldn't find source directory..."
    exit 1
fi

enabled=""
disabled=""

for name in plugins/*/NAME ; do 
    dir=${name%%/NAME}
    plugin=${dir##plugins/}
    if [ -e $dir/packaging/control/[1-9][0-9][0-9]plugin-$plugin ] ; then
	if [ ! -e $dir/etc/$plugin.ini ] ; then
	    enabled="$enabled $plugin"
	else
	    if [ -x /usr/bin/confget ] ; then
		status=$(confget -f $dir/etc/$plugin.ini plugin_status | sed -r 's/[ \t]*;.*//g')
	    else
		status=$(python 2>/dev/null <<EOF
import ConfigParser
config = ConfigParser.ConfigParser()
config.read("plugins/$plugin/etc/$plugin.ini")
print config.get("$plugin","plugin_status").strip()
EOF
) || status=error		
	    fi
	    # confget returns litteral semi-colons after values, so get rid of comments
	    if [ "$status" = "valid" ] ; then
		enabled="$enabled $plugin"
	    else
		disabled="$disabled $plugin"
	    fi
	fi
    else
	disabled="$disabled $plugin"
    fi
done

if [ "$1" = "--disabled" ] ; then
    echo $disabled
else
    echo $enabled
fi
