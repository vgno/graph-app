set :application, "graph-app"

# Configure our environments
set :stages, %w(production)
set :default_stage, "production"
set :stage_dir, File.dirname(__FILE__) + "/stages"
require 'capistrano/ext/multistage'

# Configure version control
set :scm, :git
set :repository, "git.int.vgnett.no:/git/graph-app.git"
set :branch, "master"
set :deploy_via, :remote_cache
ssh_options[:forward_agent] = true
default_run_options[:pty] = true # required for sudo

# Set deploy path
set :deploy_to, "/services/applications/#{application}"
set :apache_root, "/services/apache"
set :keep_releases, 5

# Don't use sudo for deployment, use current user
set :use_sudo, false

# Has no public assets disable bumping of timestamps
set :normalize_asset_timestamps, false

# Set up a shared logs folder
set :shared_children, %w{}

depend :remote, :command, "git"

namespace :deploy do
  namespace :dependencies do
    after "deploy:update_code", "deploy:dependencies:install"
    task :install do
      #run "php #{release_path}/composer.phar --no-dev -o --working-dir=#{release_path} --prefer-dist install"
      run "composer --no-dev -o --working-dir=#{release_path} --prefer-dist install"
      run "chmod -R g+w #{release_path}/vendor/"
    end
  end

  before "deploy:create_symlink", "deploy:config"
  namespace :config do
    task :default do
      run "mv #{release_path}/public/.htaccess-dist #{release_path}/public/.htaccess"
    end
    task :createApacheSymlink do
      set :apache_symlink, "#{apache_root}/#{application}"
      run "if [ ! -f #{apache_symlink} ]; then ln -s #{deploy_to}/current/public #{apache_symlink}; fi"
    end
  end
end
