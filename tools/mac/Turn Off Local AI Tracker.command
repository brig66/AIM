#!/bin/bash
# Turn Off Local AI Tracker
#
# The AI Visibility Tracker now runs in the cloud on the AIM Analytics
# Dashboard, every other Monday. Any copy still scheduled on this Mac asks
# the same AI engines the same questions again and pays for it twice. This
# finds every tracker schedule on this Mac, switches it off, and says what it
# found. Nothing is deleted: each schedule is moved to a backup folder so it
# can be restored.

REPORT="$HOME/Desktop/AI Tracker - Local Check $(date +%Y-%m-%d).txt"
BACKUP="$HOME/.aim-tracker/turned-off-$(date +%Y-%m-%d-%H%M%S)"
UID_NUM=$(id -u)
found=0
off=0
problems=0

exec > >(tee "$REPORT") 2>&1

say() { printf '%s\n' "$*"; }
line() { say "------------------------------------------------------------"; }

line
say "AIM AI Visibility Tracker - local computer check"
say "$(date '+%A, %B %d, %Y at %I:%M %p')"
line
say ""

# 1. Scheduled jobs (launchd) - how make_monthly_schedule.py installed them.
say "1. Checking for tracker runs scheduled on this Mac..."
tracker_plists=()
for f in "$HOME"/Library/LaunchAgents/*.plist; do
  [ -f "$f" ] || continue
  case "$(basename "$f")" in
    com.aim.tracker.*) tracker_plists+=("$f"); continue ;;
  esac
  if grep -qE "aim_ai_visibility_tracker|\.aim-tracker/schedules|aim_tracker_report" "$f" 2>/dev/null; then
    tracker_plists+=("$f")
  fi
done

if [ ${#tracker_plists[@]} -eq 0 ]; then
  say "   None found."
else
  mkdir -p "$BACKUP"
  for f in "${tracker_plists[@]}"; do
    found=$((found + 1))
    label=$(basename "$f" .plist)
    client=${label#com.aim.tracker.}
    say ""
    say "   Found: $client"
    log="$HOME/.aim-tracker/logs/$label.out.log"
    if [ -f "$log" ]; then
      say "   Last ran: $(date -r "$log" '+%B %d, %Y')"
    else
      say "   Last ran: no record of it running"
    fi
    launchctl bootout "gui/$UID_NUM" "$f" >/dev/null 2>&1 || launchctl unload "$f" >/dev/null 2>&1
    if mv "$f" "$BACKUP/"; then
      off=$((off + 1))
      say "   Turned off. (Saved a copy in $BACKUP)"
    else
      problems=$((problems + 1))
      say "   COULD NOT turn this one off - the file couldn't be moved."
    fi
  done
fi
say ""

# 2. Older-style scheduled jobs (crontab).
say "2. Checking the older scheduling system (crontab)..."
current_cron=$(crontab -l 2>/dev/null)
if printf '%s\n' "$current_cron" | grep -qE "aim_ai_visibility_tracker|aim-tracker"; then
  mkdir -p "$BACKUP"
  printf '%s\n' "$current_cron" > "$BACKUP/crontab-before.txt"
  n=$(printf '%s\n' "$current_cron" | grep -cE "aim_ai_visibility_tracker|aim-tracker")
  found=$((found + n))
  if printf '%s\n' "$current_cron" | grep -vE "aim_ai_visibility_tracker|aim-tracker" | crontab -; then
    off=$((off + n))
    say "   Found $n tracker entr$( [ "$n" -eq 1 ] && echo y || echo ies ) - turned off."
    say "   (Saved the original in $BACKUP/crontab-before.txt)"
  else
    problems=$((problems + 1))
    say "   Found $n tracker entries but COULD NOT remove them."
  fi
else
  say "   None found."
fi
say ""

# 3. Anything running right now.
say "3. Checking whether the tracker is running at this moment..."
running=$(pgrep -f "[Pp]ython[0-9.]* .*aim_ai_visibility_tracker" 2>/dev/null)
if [ -n "$running" ]; then
  kill $running 2>/dev/null
  sleep 1
  if pgrep -f "[Pp]ython[0-9.]* .*aim_ai_visibility_tracker" >/dev/null 2>&1; then
    problems=$((problems + 1))
    say "   A tracker run is in progress and COULD NOT be stopped. Restart the Mac to stop it."
  else
    say "   A tracker run was in progress - stopped."
  fi
else
  say "   Not running."
fi
say ""

# 4. Schedules installed for every user (needs an administrator to change).
say "4. Checking computer-wide schedules..."
system_hits=$(grep -lE "aim_ai_visibility_tracker|aim-tracker" /Library/LaunchAgents/*.plist /Library/LaunchDaemons/*.plist 2>/dev/null)
if [ -n "$system_hits" ]; then
  problems=$((problems + 1))
  say "   Found tracker schedules this tool can't change without an administrator:"
  printf '   %s\n' $system_hits
  say "   Send this report to Claude and ask it to handle these."
else
  say "   None found."
fi
say ""

line
if [ "$found" -eq 0 ] && [ "$problems" -eq 0 ]; then
  say "RESULT: Nothing on this Mac runs the AI tracker. There is no duplicate"
  say "work - the dashboard is the only thing running it."
elif [ "$problems" -eq 0 ]; then
  say "RESULT: Found $found tracker schedule(s) on this Mac and turned off $off."
  say "The dashboard is now the only thing running the AI tracker."
else
  say "RESULT: Found $found, turned off $off, but $problems item(s) need attention"
  say "(marked above). Send this report to Claude."
fi
line
say ""
say "This report was saved to your Desktop:"
say "  $(basename "$REPORT")"
say ""
say "You can close this window."
