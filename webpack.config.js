const path = require('path')
const fs   = require('fs')
const HtmlWebpackPlugin = require('html-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')
var webpack = require("webpack");



module.exports = {
  mode: 'development',
  devtool: 'source-map',
  entry: './src/js/index.js',

  module: {
    rules: [
      {
        test: /\.css$/i,
        use: ["style-loader", "css-loader"],
      },
      {
        test: /\.scss$/,
        use: ['style-loader', 'css-loader','sass-loader']
      },
      {
        test: /\.(woff(2)?|ttf|eot|svg)(\?v=\d+\.\d+\.\d+)?$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: '[name].[ext]',
              outputPath: 'fonts/'
            }
          }
        ]
      }
    ]
  },
  output: {
    path: path.join(__dirname, 'dist'),
    filename: 'index.js',
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: './src/index.html'
    }),
    new webpack.ProvidePlugin({
        $: "jquery",
        jQuery: "jquery"
    }),
    new CopyWebpackPlugin({
      patterns: [
        {
          from: 'src/php',
          to: '.',
          globOptions: {
            ignore: ['**/env_vars.php']
          }
        },
        {
          // Only copy toggles.json if it doesn't already exist in dist
          from: 'src/toggles.json',
          to: '.',
          filter: () => !fs.existsSync(path.join(__dirname, 'dist', 'toggles.json'))
        },
        {
          from: 'src/stubs',
          to: '.',
          globOptions: { dot: true, ignore: ['**/note.html', '**/wordlist.json'] },
          noErrorOnMissing: true
        },
        {
          // Only copy note.html if it doesn't already exist in dist
          from: 'src/stubs/notes/note.html',
          to: 'notes/note.html',
          noErrorOnMissing: true,
          filter: () => !fs.existsSync(path.join(__dirname, 'dist', 'notes', 'note.html'))
        },
        {
          // Only copy wordlist.json if it doesn't already exist in dist
          from: 'src/stubs/word/wordlist.json',
          to: 'word/wordlist.json',
          noErrorOnMissing: true,
          filter: () => !fs.existsSync(path.join(__dirname, 'dist', 'word', 'wordlist.json'))
        },
        {
          // Only copy schedule.json if it doesn't already exist in dist
          from: 'src/stubs/schedule.json',
          to: 'schedule.json',
          noErrorOnMissing: true,
          filter: () => !fs.existsSync(path.join(__dirname, 'dist', 'schedule.json'))
        }
      ]
    })
  ],
}
