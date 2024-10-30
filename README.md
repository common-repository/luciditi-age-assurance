### Making changes ( development )

Want to make changes to the source code? `cd` into the project folder, install dependencies using `npm install`. Right after insalling the dependencies, run `npm run dev` to prepare the plugin for development, this is mainly to run the processes responsible for changing API URLs from production URLs to Development URLs. Finally, watch for JS changes using the command `npm run watch`.

Once the command `npm run watch` is executed, you can start making changes to the javascript files and the `Grunt` task runner will automatically generate minified version of the modified files to the correct location.

Once done and you are ready to deploy the plugin to production, run `npm run build`. This will generate CSS and JS resources without source mapping, replace API URLs, and generate the translation files.

**Note 1:** Always run `npm run build` before pushing changes.
**Note 2:** If you want to create a UAT version, run `npm run uat`, this will generate an exact copy of production version, except for the API URLs which will be UAT URLs.