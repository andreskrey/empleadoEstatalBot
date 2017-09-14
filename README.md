# empleadoEstatalBot

Also known as 'the bot that steals content from other sites and posts it as a comment on reddit'

## Installation

1) \> git clone
2) \> composer install
3) Create your config.yml in app/config
4) `empleadoEstatalBot.php config:seed`
5) Add a cron for `empleadoEstatalBot.php get:start`, `empleadoEstatalBot.php fetch:start` and `empleadoEstatalBot.php post:start`

## Usage

The bot has three main workers available on the CLI

- `empleadoEstatalBot.php get:start`: Gets all the new posts on the monitored subreddits
- `empleadoEstatalBot.php fetch:start`: Retrieves the newspaper text of the things with status TO_FETCH, converts it to markdown and signs the text.
- `empleadoEstatalBot.php post:start`: Posts all comments with status TO_POST. You can do this step and the previous step with one call adding the `--pre-fetch` argument.

The recommended way to monitor and post comments on a subreddit is to set a cron on odd minutes to get posts and another on even minutes to fetch and post comments.

Workers will refuse to work if the same worker is already doing stuff. Let's say your post --pre-fetch worker is taking its sweet time to do its job and the next cron starts up. In this case the post worker will refuse because the previous iteration didn't finish yet. This is controlled through lock files in the tmp folder. If one worker refuses to work even if you are sure there's no other instance in memory, just delete all `.lock` files in the tmp folder. Or wait 10 minutes because locks expire after that period of time.