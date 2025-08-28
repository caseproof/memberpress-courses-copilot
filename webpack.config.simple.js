const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

// Simple webpack configuration for future modular JavaScript development
// Currently, the existing JS files in assets/js/ are standalone and don't require bundling

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    // Add entries here when you have modular JavaScript that needs bundling
    entry: {
      // Example: 'bundle-name': './src/js/entry-file.js'
    },
    output: {
      path: path.resolve(__dirname, 'dist/js'),
      filename: '[name].min.js',
      clean: true
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        }
      ]
    },
    optimization: {
      minimize: isProduction,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false,
            },
          },
          extractComments: false,
        }),
      ],
    },
    devtool: isProduction ? false : 'source-map',
    externals: {
      jquery: 'jQuery',
      wp: 'wp'
    }
  };
};