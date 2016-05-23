This admin tool allows import/export of competency frameworks using a
comma separated value file (CSV).

This imports and exports all data contained in the competency 
framework including related competencies, and any configured 
competency rules.

Install from git:

Navigate to Moodle root folder
git clone git://github.com/damyon/moodle-tool_lpimportcsv.git admin/tool/lpimportcsv
cd admin/tool/lpimportcsv
git branch -a
git checkout master
Click the 'Notifications' link on the frontpage administration block or from your Moodle root folder run: php admin/cli/upgrade.php if you have access to a command line interpreter.
