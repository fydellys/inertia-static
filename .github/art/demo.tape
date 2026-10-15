# The README GIF, recorded with VHS (https://github.com/charmbracelet/vhs) against the demo stack
# (examples/demo-docker, nginx CDN on :8088). VHS image with curl and the Docker CLI added:
#   docker run --rm --network container:inertia-static-demo-cdn-1 -v /var/run/docker.sock:/var/run/docker.sock \
#     -v "$PWD/.github/art:/vhs:ro" -v "$PWD/.github/art:/out" <image> /vhs/demo.tape

Output "/out/demo.gif"

Set Shell "bash"
Set FontSize 20
Set Width 1300
Set Height 600
Set Padding 24
Set Theme "Catppuccin Mocha"
Set TypingSpeed 28ms
Set WindowBar Colorful
Set CursorBlink false

Hide
Type `export PS1='\[\e[38;5;141m\]$\[\e[0m\] '; php() { docker exec -i inertia-static-demo-app-1 php "$@"; }; clear`
Enter
Show

Type "# /blog/{post} is an ISR route, tagged post:{id}"
Enter
Type `alias visit='curl -so /dev/null -w "%{http_code}  %header{x-cache-status}  %{time_total}s\n"'`
Enter
Sleep 500ms

Type "visit localhost:8088/blog/post-1   # 1st visit: Laravel renders it"
Enter
Sleep 1500ms
Type "visit localhost:8088/blog/post-1   # served by the CDN"
Enter
Sleep 1200ms
Type "visit localhost:8088/blog/post-1"
Enter
Sleep 2s

Type "# the post is edited: purge every page tagged post:1"
Enter
Type "php artisan inertia-static:purge --tags=post:1"
Enter
Sleep 2500ms

Type "visit localhost:8088/blog/post-1   # rendered again"
Enter
Sleep 1500ms
Type "visit localhost:8088/blog/post-1"
Enter
Sleep 4s
