const path = require('path');

const libsodiumCjs = path.resolve(
  __dirname,
  'node_modules/libsodium-wrappers-sumo/dist/modules-sumo/libsodium-wrappers.js'
);

/** @type {import('next').NextConfig} */
const nextConfig = {
  env: {
    NEXT_PUBLIC_BLOCKFROST_KEY: process.env.NEXT_PUBLIC_BLOCKFROST_KEY,
  },
  webpack: function (config, { isServer }) {
    if (!isServer) {
      config.experiments = {
        asyncWebAssembly: true,
        layers: true,
      };
      // Tell webpack this target supports async functions (required for asyncWebAssembly)
      config.output = config.output || {};
      config.output.environment = {
        ...config.output.environment,
        asyncFunction: true,
      };
    }

    // The ESM build of libsodium-wrappers-sumo references a missing file.
    // Force webpack to use the working CJS build instead.
    config.resolve.alias['libsodium-wrappers-sumo'] = libsodiumCjs;

    return config;
  },
};

module.exports = nextConfig;
