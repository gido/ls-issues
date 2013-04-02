set :application, "ls-issues"
set :deploy_to,  "/home/antistatique/www/#{application}"
default_run_options[:pty] = true

set :scm, :git
set :repository,  "git@github.com:gido/ls-issues.git"
set :deploy_via, :remote_cache
set :branch, "master"
set :keep_releases, 3

server "46.105.52.113", :app, :web, :db, :primary => true

set :ssh_options, {:forward_agent => true, :port => 22}
set :user, "antistatique"
set :use_sudo, false

namespace :myproject do

    task :vendors do
        run "curl -s http://getcomposer.org/installer | php -- --install-dir=#{release_path}"
        run "cd #{release_path} && #{release_path}/composer.phar install"
    end

    task :uploads do
        run "mkdir -p #{shared_path}/web/uploads"
        run "chmod -R 775 #{shared_path}/web/uploads"
        run "ln -nfs #{shared_path}/web/uploads #{release_path}/web/uploads"
    end

    task :shared_symlinks do
    	run "ln -s #{shared_path}/resources/config/prod.php #{current_path}/resources/config/prod.php"
    end
end
	
after "deploy:update_code", "myproject:vendors"
after "deploy:create_symlink", "myproject:shared_symlinks"
after "deploy:update", "deploy:cleanup" 
