#!/usr/bin/env bash
#
# Deploy the NKS osTicket API plugin into an osTicket installation.
#
# Usage:
#   OSTICKET_WEB=/path/to/osticket/upload ./deploy.sh
#
# OSTICKET_WEB must point at the osTicket web root (the directory that
# contains main.inc.php and the api/ folder). The script copies the plugin
# into include/plugins/, installs the single web entry into api/, and adds the
# .htaccess bypass rule if it is missing. Owner/permissions are left to the
# caller (run chown afterwards to match your web user).

set -euo pipefail

WEB="${OSTICKET_WEB:?set OSTICKET_WEB to the osTicket web root (dir with main.inc.php)}"
SRC="$(cd "$(dirname "$0")" && pwd)"

[ -f "$WEB/main.inc.php" ] || { echo "error: $WEB/main.inc.php not found — wrong OSTICKET_WEB?" >&2; exit 1; }
[ -d "$WEB/api" ] || { echo "error: $WEB/api not found" >&2; exit 1; }

PLUGIN_DIR="$WEB/include/plugins/nks-osticket-api"
echo "-> installing plugin into $PLUGIN_DIR"
mkdir -p "$PLUGIN_DIR"
cp -r "$SRC/src" "$PLUGIN_DIR/"
cp "$SRC/plugin.php" "$SRC/config.php" "$SRC/class.NksOsTicketApiPlugin.php" "$PLUGIN_DIR/"

echo "-> installing web entry into $WEB/api/nks-osticket.php"
cp "$SRC/web/nks-osticket.php" "$WEB/api/nks-osticket.php"

HT="$WEB/api/.htaccess"
if [ -f "$HT" ] && ! grep -q "nks-osticket" "$HT"; then
  echo "-> adding .htaccess bypass rule"
  cp -p "$HT" "$HT.nks-bak"
  # Insert our bypass right after "RewriteEngine On" so it precedes the
  # default osTicket dispatcher rule at the end of the file.
  awk '
    { print }
    tolower($0) ~ /rewriteengine[ \t]+on/ && !done {
      print "";
      print "# NKS osTicket API — bypass default dispatcher for our single entry";
      print "RewriteRule ^nks-osticket\\.php.*$ - [L]";
      done = 1;
    }
  ' "$HT.nks-bak" > "$HT"
else
  echo "-> .htaccess already has the bypass (or no .htaccess present); skipping"
fi

echo "done. Remember to chown api/nks-osticket.php and the plugin dir to your web user."
