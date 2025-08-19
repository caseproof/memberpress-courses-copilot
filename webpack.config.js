const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    entry: {
      'admin': './src/assets/js/admin.js',
      'frontend': './src/assets/js/frontend.js',
      'ai-copilot': './src/assets/js/ai-copilot.js'
    },
    output: {
      path: path.resolve(__dirname, 'assets/js'),
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
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        },
        {
          test: /\.scss$/,
          use: ['style-loader', 'css-loader', 'sass-loader']
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
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src/assets/js'),
        '@components': path.resolve(__dirname, 'src/assets/js/components'),
        '@utils': path.resolve(__dirname, 'src/assets/js/utils')
      }
    },
    externals: {
      jquery: 'jQuery',
      wp: 'wp'
    }
  };
};