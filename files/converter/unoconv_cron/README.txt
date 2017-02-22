This is a file conversion plugin that uses unoconv to convert files, but does it in an asynchronous fashion.

Files submitted to be converted as held in the core file_conversion table, and return as a pending/in progress conversion.

By default, they will then be converted on the next cron run, at which point the conversions will be available for use.


For testing and development, it may be helpful to disable the \fileconverter_unoconv_cron\task\convert_files_task task (in Site Admin > Server > Scheduled Tasks), and then trigger the conversions by calling the CLI script files/converter/unoconv_cron/cli/convert_pending.php.

This allows you specifically control how long a conversion sits as pending/in progress before it is actually converted.