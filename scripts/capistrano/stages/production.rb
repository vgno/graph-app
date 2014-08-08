# Set up involved servers
role :web, "vg-ws-01", :primary => true
role :web, "vg-ws-02"

after "deploy:create_symlink", "deploy:config:createApacheSymlink"