#MySQL Schema Version Control

SQLGit is a simple PHP tool that makes it very easy to version control your database structure using Git.

##Usage
SQLGit is run from the command line using PHP and takes 3 arguments, plus an action. *Please Note : If you're using Windows, you need to make sure PHP is set in your `PATH` system variable*.

The three required arguments are:

<table>
	<thead>
		<tr>
			<th>Argument</th>
			<th></th>
			<th>Description</th>
		</tr>
	</thead>

	<tbody>
		<tr>
			<th>-u</th>
			<td>User</td>
			<td>This is the username used to log into your MySQL database.</td>
		</tr>
		<tr>
			<th>-p</th>
			<td>Password</td>
			<td>This is the password use to log into your MySQL database.</td>
		</tr>
		<tr>
			<th>-d</th>
			<td>Database</td>
			<td>The MySQL database you want to select for version control.</td>
		</tr>
	</tbody>
</table>

You then have two arguments : `-s` for **save** or `-m` for **merge**. 

###Saving

Using the **save** action will take a snapshot of your database's structure and save it in a file called `schema`. This is the file which then becomes part of your repository and is version controlled by Git.

###Merging

Using the **merge** action will compare your current database structure to the one saved in your repository and will then alter the database as required. It does not impact your data unless a column or table is dropped.

##Examples
###Saving database structure

`php sqlgit.php -u [USERNAME] -p [PASSWORD] -d [DATABASE] -s`

###Merging database structure

`php sqlgit.php -u [USERNAME] -p [PASSWORD] -d [DATABASE] -m`

##Git Hooks
You can automate the process of MySQL version control by using Git hooks. *Please note that Github for Windows currently does not support pre-commit hooks.*

To automatically save and commit your database structure with your other code changes, create a file called `pre-commit` in the git hooks folder (`.git/hooks/`) with the following commands:

	#!/bin/sh

	php sqlgit.php -u [USERNAME] -p [PASSWORD] -d [DATABASE] -s
	git add schema

To merge your database structure whenever you merge or checkout a branch, create two files called `post-merge` and `post-checkout` with the following commands:

	#!/bin/sh

	php sqlgit.php -u [USERNAME] -p [PASSWORD] -d [DATABASE] -m