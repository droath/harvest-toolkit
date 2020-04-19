# Harvest Toolkit

Harvest Toolkit is a CLI utility to streamline reporting and manipulation of time entries. The responsibilities of the project will grow as it matures. Please submit feature request in the issue queue.

## Quick Start

Install the Harvest Toolkit using composer.

```
composer global req droath/harvest-toolkit
```

You'll need to authenticate with the Harvest service. Input your Account ID and [Personal API Token](https://id.getharvest.com/developers).

```
ht login
```

## Command Documentation

### Login

Login to the Harvest service using an account ID and personal API token.

```
ht login
```
**Options:**

```
--reauthentication: Set if you need to reauthenticate using a different Harvest account.
```

**Note:** All commands require authentication prior.

### Timesheet Adjustment

Adjust time entries stored in Harvest.

```
ht timesheet:adjust
```
**Arguments:**

```
timespan - The timespan on which to adjust the time sheet. [default: "today"]
```

**Options:**

```
--max-hours=MAX-HOURS  Set the required amount of hours needed per day. [default: 8]
```
**Examples:**

Adjust all time entries that are between now and yesterday.

```
ht timesheet:adjust yesterday
```
Adjust all time entries that are between now and 1 week ago.

```
ht timesheet:adjust "1 week ago"
```

### Timesheet Report

Show all the time entries stored in Harvest.

**Arguments:**

```
timespan - The timespan to show the time sheet report for. [default: "today"]
```
**Options:**

```
--only-billable   Only show time entries that are billable in the report.
--oneline         Show the total time entries report in a condensed line.
--copy            Copy last output to clipboard (only supported when used with --oneline).
```

**Examples:**

Show all billable time entries from yesterday on one line and copy to the clipboard.

```
ht timesheet:report yesterday --only-billable --oneline --copy
```

Show all billable/non-billable time entries from 1 week ago.

```
ht timesheet:report "1 week ago"
```
