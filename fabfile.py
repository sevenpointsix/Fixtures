import os
from fabric.api import *
from fabric.contrib import console

########################################################################
# local commands
########################################################################

def commit():
	"""
	Commits changes to the local repository, prompting for a commit message if necessary
	Usage:
	fab commit
	Note that in most situations, calling fab push will suffice
	"""
	msg = prompt("Commit message:", default="No message")
	_commit(msg)

def _commit(msg=None):

	local("git add .", capture=False)
	
	with settings(hide('warnings'),warn_only=True):
		if(msg):
			local("git commit -am \"%s\"" % msg, capture=False)
		else:
			local("git commit -am \"No message\"", capture=False)

def push():
	"""
	Commits changes and then pushes them to the remote repository
	Usage:
	fab push
	"""
	with hide('running'):
		print("Pushing files to remote repository")
		commit()
		local("git push origin master", capture=False)

def pull():
	"""
	Pulls changes from the remote repository, and updates the local copy
	Usage:
	fab pull
	"""
	with hide('running'):
		print("Pulling files from remote repository")
		local("git pull")

