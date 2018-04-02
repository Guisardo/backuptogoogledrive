# Google Drive Site Backups

A cli tool to backup a site's codebase and database to google drive.

## Installation

 - Clone repository content
 - Run `composer install`

## Usage

 - Add server settings based on `globals.ini.sample`
 - Add one or more app configurations based on `example.ini.sample`
 - Add cron job to execute `php backuptogoogledrive.php`

You can also force the execution of a specific app backup using the `--app=example` parameter.

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request :D

## TODO

 - The docs
 - Locking code to prevent overlapping executions
 - LOGS!!
 - Notifications
 - UI for de configs and for monitoring
 - Encrypt .scrt files

## History

TODO: Write history

## Credits

TODO: Write credits
Based on ...

## License

TODO: Write license
